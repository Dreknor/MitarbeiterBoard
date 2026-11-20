<?php

namespace App\Services\Posts;

use App\Models\Group;
use App\Models\Post;
use App\Models\PostLesestatus;
use App\Models\User;
use App\Notifications\NachrichtErinnerung;
use App\Notifications\NachrichtVeroeffentlicht;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Nachrichten (Dashboard + Gruppen) mit optionaler Lesebestätigung – einzige Stelle für alle Regeln.
 *
 * - Empfänger:innen sind die aktuellen Mitglieder der Zielgruppen (group_user) außer der verfassenden
 *   Person; gelöschte Konten zählen nicht. Die Liste ist dynamisch: Neue Mitglieder müssen ebenfalls
 *   bestätigen (eigene Frist), wer ausscheidet und schon bestätigt hat, bleibt in der Liste.
 * - Veröffentlichte Nachrichten werden nur archiviert; gelöscht werden nur Entwürfe.
 * - Lesebestätigungen: ausdrücklicher Klick, Erinnerung 2 Tage vor und am Fristtag, manuell max. 1× täglich.
 */
class PostService
{
    /** Mindestfrist für später hinzugekommene Empfänger:innen (Tage). */
    public const MINDESTFRIST_TAGE = 7;

    /** Automatische Erinnerung so viele Tage vor der Frist. */
    public const ERINNERUNG_VORAB_TAGE = 2;

    /** @var array<int, array<int, int>> */
    private array $ungeleseneJeGruppeCache = [];

    // ------------------------------------------------------------------
    // Rechte rund ums Verfassen
    // ------------------------------------------------------------------

    /**
     * Gruppen, in die die Person posten darf: eigene Gruppen mit "create posts",
     * selbst angelegte Gruppen immer, mit "manage posts" alle Gruppen.
     *
     * @return Collection<int, Group>
     */
    public function zielGruppen(User $user): Collection
    {
        if ($user->can('manage posts')) {
            return Group::query()->orderBy('name')->get();
        }

        $gruppen = $user->can('create posts') ? $user->groups_rel()->get() : collect();

        return $gruppen
            ->concat(Group::query()->where('creator_id', $user->id)->get())
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function darfPosten(User $user): bool
    {
        return $user->can('create posts')
            || $user->can('manage posts')
            || Group::query()->where('creator_id', $user->id)->exists();
    }

    public function darfBestaetigungAnfordern(User $user): bool
    {
        return $user->can('request read confirmation') || $user->can('manage posts');
    }

    // ------------------------------------------------------------------
    // Empfänger
    // ------------------------------------------------------------------

    /**
     * Aktuelle Empfänger:innen: Mitglieder der Zielgruppen ohne die verfassende Person.
     *
     * @return Builder<User>
     */
    public function empfaengerQuery(Post $post): Builder
    {
        $gruppen = $post->groups()->pluck('groups.id')->all();

        return User::query()
            ->whereIn('id', DB::table('group_user')->whereIn('group_id', $gruppen)->select('user_id'))
            ->when($post->author_id, fn (Builder $q) => $q->where('id', '!=', $post->author_id));
    }

    public function istEmpfaenger(Post $post, User $user): bool
    {
        if ((int) $post->author_id === (int) $user->id) {
            return false;
        }

        return DB::table('group_user')
            ->where('user_id', $user->id)
            ->whereIn('group_id', $post->groups()->pluck('groups.id'))
            ->exists();
    }

    // ------------------------------------------------------------------
    // Anlegen, Bearbeiten, Veröffentlichen, Archivieren
    // ------------------------------------------------------------------

    /**
     * Legt eine Nachricht an oder aktualisiert sie.
     *
     * @param  array{header: string, kurzfassung?: ?string, text?: ?string, groups: array<int, int|string>,
     *               released?: mixed, auf_dashboard?: mixed, sichtbar_bis?: ?string,
     *               bestaetigung_erforderlich?: mixed, bestaetigung_frist?: ?string}  $daten
     * @param  array<int, UploadedFile>  $dateien
     * @param  bool  $bestaetigungenZuruecksetzen  wesentliche Änderung: alle müssen erneut bestätigen
     */
    public function speichern(User $actor, array $daten, array $dateien = [], ?Post $post = null, bool $bestaetigungenZuruecksetzen = false): Post
    {
        $gruppen = $this->pruefeGruppen($actor, $daten['groups'] ?? [], $post);

        $neu = $post === null;
        $post ??= new Post(['author_id' => $actor->id]);
        if ($neu) {
            $post->author_id = $actor->id;
        }

        $warVeroeffentlicht = $post->exists && $post->released;
        $warBestaetigung = $post->exists && $post->bestaetigung_erforderlich;
        $alteFrist = $post->bestaetigung_frist?->toDateString();

        $post->header = trim((string) $daten['header']);
        $post->kurzfassung = filled($daten['kurzfassung'] ?? null) ? trim((string) $daten['kurzfassung']) : null;
        $post->text = $daten['text'] ?? null;
        $post->auf_dashboard = array_key_exists('auf_dashboard', $daten) ? (bool) $daten['auf_dashboard'] : ($post->auf_dashboard ?? true);
        $post->sichtbar_bis = filled($daten['sichtbar_bis'] ?? null) ? $daten['sichtbar_bis'] : null;

        if ($this->darfBestaetigungAnfordern($actor)) {
            $post->bestaetigung_erforderlich = (bool) ($daten['bestaetigung_erforderlich'] ?? false);
            $post->bestaetigung_frist = $post->bestaetigung_erforderlich && filled($daten['bestaetigung_frist'] ?? null)
                ? $daten['bestaetigung_frist']
                : null;
        } elseif ($neu) {
            $post->bestaetigung_erforderlich = false;
            $post->bestaetigung_frist = null;
        }

        // Einmal veröffentlicht bleibt veröffentlicht (danach nur noch archivieren).
        $sollVeroeffentlicht = $post->warVeroeffentlicht() || (bool) ($daten['released'] ?? false);
        $post->released = $sollVeroeffentlicht;

        DB::transaction(function () use ($post, $gruppen, $dateien) {
            $post->save();
            $post->groups()->sync($gruppen);
            $this->dateienAnhaengen($post, $dateien);
        });

        $post->refresh()->load('groups', 'author');

        if ($sollVeroeffentlicht && ! $warVeroeffentlicht) {
            $this->nachVeroeffentlichung($post);

            return $post;
        }

        if (! $sollVeroeffentlicht || ! $post->bestaetigung_erforderlich || $post->archived) {
            return $post;
        }

        if (! $warBestaetigung) {
            // Lesebestätigung nachträglich angefordert
            $post->forceFill(['bestaetigung_angefordert_at' => now()])->save();
            $this->zuweisen($post, $this->empfaengerQuery($post)->get(), $post->bestaetigung_frist, true, NachrichtVeroeffentlicht::GRUND_ANGEFORDERT);
        } elseif ($bestaetigungenZuruecksetzen) {
            $this->bestaetigungenZuruecksetzen($post);
        } else {
            if ($alteFrist !== $post->bestaetigung_frist?->toDateString()) {
                $this->fristAnpassen($post, $alteFrist);
            }
            // Neu hinzugekommene Zielgruppen
            $this->abgleichen($post);
        }

        return $post;
    }

    public function veroeffentlichen(Post $post): Post
    {
        if ($post->released) {
            return $post;
        }

        $post->released = true;
        $post->save();
        $post->load('groups', 'author');
        $this->nachVeroeffentlichung($post);

        return $post;
    }

    public function archivieren(Post $post): void
    {
        $post->forceFill(['archived' => true, 'archiviert_at' => now()])->save();
    }

    public function wiederherstellen(Post $post): void
    {
        $post->forceFill(['archived' => false, 'archiviert_at' => null])->save();
    }

    /**
     * Löscht einen Entwurf. Veröffentlichte Nachrichten werden archiviert.
     *
     * @return bool true = gelöscht, false = stattdessen archiviert
     */
    public function loeschen(Post $post): bool
    {
        if ($post->warVeroeffentlicht()) {
            $this->archivieren($post);

            return false;
        }

        $post->delete();

        return true;
    }

    // ------------------------------------------------------------------
    // Lesen und Bestätigen
    // ------------------------------------------------------------------

    public function statusFuer(Post $post, User $user): ?PostLesestatus
    {
        return PostLesestatus::query()->where('post_id', $post->id)->where('user_id', $user->id)->first();
    }

    public function alsGelesen(Post $post, User $user): ?PostLesestatus
    {
        if (! $post->released || (int) $post->author_id === (int) $user->id) {
            return $this->statusFuer($post, $user);
        }

        $status = PostLesestatus::query()->firstOrNew(['post_id' => $post->id, 'user_id' => $user->id]);
        if ($status->gelesen_at === null) {
            $status->gelesen_at = now();
            $status->save();
        }

        return $status;
    }

    public function bestaetigen(Post $post, User $user): PostLesestatus
    {
        if (! $post->bestaetigung_erforderlich || ! $post->released || $post->archived || ! $this->istEmpfaenger($post, $user)) {
            throw ValidationException::withMessages(['post' => 'Für diese Nachricht ist keine Bestätigung möglich.']);
        }

        $status = PostLesestatus::query()->firstOrNew(['post_id' => $post->id, 'user_id' => $user->id]);
        $status->gelesen_at ??= now();
        $status->zugewiesen_at ??= now();
        $status->bestaetigt_at ??= now();
        $status->save();

        return $status;
    }

    // ------------------------------------------------------------------
    // Bestätigungsliste
    // ------------------------------------------------------------------

    /**
     * Bestätigungsliste: aktuelle Empfänger:innen (bestätigt/offen/überfällig) plus Ausgeschiedene,
     * die bereits bestätigt haben. Offene zuerst, dann nach Name.
     *
     * @return Collection<int, array{user: User, status: string, bestaetigt_at: ?Carbon, frist: ?Carbon, ausgeschieden: bool}>
     */
    public function bestaetigungsliste(Post $post): Collection
    {
        $heute = Carbon::today();
        $zeilen = PostLesestatus::query()->where('post_id', $post->id)->get()->keyBy('user_id');
        $empfaenger = $this->empfaengerQuery($post)->orderBy('name')->get();

        $liste = $empfaenger->map(function (User $user) use ($zeilen, $post, $heute) {
            /** @var PostLesestatus|null $zeile */
            $zeile = $zeilen->get($user->id);
            $frist = $zeile?->frist ?? $post->bestaetigung_frist;
            $bestaetigt = $zeile?->bestaetigt_at;

            return [
                'user' => $user,
                'status' => $bestaetigt ? 'bestaetigt' : ($frist !== null && $frist->lt($heute) ? 'ueberfaellig' : 'offen'),
                'bestaetigt_at' => $bestaetigt,
                'frist' => $frist,
                'ausgeschieden' => false,
            ];
        });

        $ausgeschiedene = $zeilen
            ->filter(fn (PostLesestatus $z) => $z->bestaetigt_at !== null && ! $empfaenger->contains('id', $z->user_id))
            ->map(fn (PostLesestatus $z) => $z->user)
            ->filter();

        foreach ($ausgeschiedene as $user) {
            $zeile = $zeilen->get($user->id);
            $liste->push([
                'user' => $user,
                'status' => 'bestaetigt',
                'bestaetigt_at' => $zeile->bestaetigt_at,
                'frist' => $zeile->frist,
                'ausgeschieden' => true,
            ]);
        }

        $rang = ['ueberfaellig' => 0, 'offen' => 1, 'bestaetigt' => 2];

        return $liste
            ->sort(fn ($a, $b) => [$rang[$a['status']], mb_strtolower($a['user']->name)] <=> [$rang[$b['status']], mb_strtolower($b['user']->name)])
            ->values();
    }

    /**
     * @return array{gesamt: int, bestaetigt: int, offen: int, ueberfaellig: int}
     */
    public function statistik(Post $post): array
    {
        $liste = $this->bestaetigungsliste($post)->where('ausgeschieden', false);

        return [
            'gesamt' => $liste->count(),
            'bestaetigt' => $liste->where('status', 'bestaetigt')->count(),
            'offen' => $liste->where('status', 'offen')->count(),
            'ueberfaellig' => $liste->where('status', 'ueberfaellig')->count(),
        ];
    }

    // ------------------------------------------------------------------
    // Erinnerungen
    // ------------------------------------------------------------------

    public function darfHeuteErinnern(Post $post): bool
    {
        return $post->manuell_erinnert_at === null || ! $post->manuell_erinnert_at->isToday();
    }

    /**
     * Manuelle Erinnerung an alle Offenen (höchstens einmal pro Tag).
     *
     * @return int Anzahl erinnerter Personen
     */
    public function erinnern(Post $post): int
    {
        if (! $this->darfHeuteErinnern($post)) {
            throw ValidationException::withMessages(['post' => 'Heute wurde bereits an diese Nachricht erinnert.']);
        }

        $offene = $this->bestaetigungsliste($post)
            ->where('ausgeschieden', false)
            ->where('status', '!=', 'bestaetigt')
            ->pluck('user');

        if ($offene->isNotEmpty()) {
            Notification::send($offene, new NachrichtErinnerung($post));
        }

        $post->forceFill(['manuell_erinnert_at' => now()])->save();

        return $offene->count();
    }

    /**
     * Täglich: automatische Erinnerungen 2 Tage vor der (persönlichen) Frist und am Fristtag.
     * Nach der Frist gibt es keine automatischen Erinnerungen mehr.
     *
     * @return int Anzahl verschickter Erinnerungen
     */
    public function erinnerungenVersenden(): int
    {
        $heute = Carbon::today();
        $anzahl = 0;

        $posts = Post::query()->veroeffentlicht()->aktiv()->where('bestaetigung_erforderlich', true)->with('groups')->get();

        foreach ($posts as $post) {
            $this->abgleichen($post);
            $empfaenger = $this->empfaengerQuery($post)->pluck('id');

            $offen = PostLesestatus::query()
                ->where('post_id', $post->id)
                ->whereNull('bestaetigt_at')
                ->whereNotNull('frist')
                ->whereIn('user_id', $empfaenger)
                ->with('user')
                ->get();

            foreach ($offen as $zeile) {
                if ($zeile->user === null) {
                    continue;
                }

                if ($zeile->frist->isSameDay($heute) && $zeile->erinnert_frist_at === null) {
                    $zeile->user->notify(new NachrichtErinnerung($post, true));
                    $zeile->forceFill(['erinnert_frist_at' => now()])->save();
                    $anzahl++;
                } elseif ($zeile->frist->gt($heute)
                    && $heute->copy()->addDays(self::ERINNERUNG_VORAB_TAGE)->gte($zeile->frist)
                    && $zeile->erinnert_vorab_at === null) {
                    $zeile->user->notify(new NachrichtErinnerung($post));
                    $zeile->forceFill(['erinnert_vorab_at' => now()])->save();
                    $anzahl++;
                }
            }
        }

        return $anzahl;
    }

    // ------------------------------------------------------------------
    // Abgleich (neue Mitglieder) und Ablauf
    // ------------------------------------------------------------------

    /**
     * Weist allen Empfänger:innen ohne Zuweisung die Bestätigung zu (z. B. neue Gruppenmitglieder)
     * und benachrichtigt sie. Ohne Post: alle aktiven Nachrichten mit Lesebestätigung.
     *
     * @return int Anzahl neu zugewiesener Personen
     */
    public function abgleichen(?Post $post = null): int
    {
        if ($post === null) {
            return Post::query()->veroeffentlicht()->aktiv()->where('bestaetigung_erforderlich', true)->get()
                ->sum(fn (Post $p) => $this->abgleichen($p));
        }

        if (! $post->released || $post->archived || ! $post->bestaetigung_erforderlich) {
            return 0;
        }

        $zugewiesen = PostLesestatus::query()->where('post_id', $post->id)->whereNotNull('zugewiesen_at')->pluck('user_id');
        $neue = $this->empfaengerQuery($post)->whereNotIn('id', $zugewiesen)->get();

        if ($neue->isEmpty()) {
            return 0;
        }

        $this->zuweisen($post, $neue, $this->fristFuerNachzuegler($post), true, NachrichtVeroeffentlicht::GRUND_BEITRITT);

        return $neue->count();
    }

    /** Abgleich für alle aktiven Nachrichten einer Gruppe (nach Aufnahme neuer Mitglieder). */
    public function abgleichenFuerGruppe(Group $group): int
    {
        return $group->posts()->veroeffentlicht()->aktiv()->where('bestaetigung_erforderlich', true)->get()
            ->sum(fn (Post $p) => $this->abgleichen($p));
    }

    /**
     * Täglich: Nachrichten nach „sichtbar bis“ archivieren – mit offener Bestätigungsfrist erst danach.
     */
    public function abgelaufeneArchivieren(): int
    {
        $heute = Carbon::today();

        $posts = Post::query()->aktiv()->veroeffentlicht()
            ->whereNotNull('sichtbar_bis')
            ->whereDate('sichtbar_bis', '<', $heute)
            ->get()
            ->filter(fn (Post $p) => ! $p->bestaetigung_erforderlich
                || $p->bestaetigung_frist === null
                || $p->bestaetigung_frist->lt($heute));

        foreach ($posts as $post) {
            $this->archivieren($post);
        }

        return $posts->count();
    }

    // ------------------------------------------------------------------
    // Abfragen für Dashboard, Gruppe, Zähler
    // ------------------------------------------------------------------

    /**
     * Dashboard: Nachrichten mit „auf Dashboard“ + eigene Entwürfe; offene eigene Bestätigungen zuerst.
     *
     * @return Collection<int, Post>
     */
    public function dashboardNachrichten(User $user, int $limit = 15): Collection
    {
        $offen = $this->offeneBestaetigungIds($user);

        $posts = Post::query()
            ->sichtbarFuer($user)
            ->aktiv()
            ->where(fn (Builder $q) => $q->where('auf_dashboard', true)->orWhereIn('posts.id', $offen))
            ->with(['author', 'groups', 'media'])
            ->orderByDesc(DB::raw('COALESCE(veroeffentlicht_at, created_at)'))
            ->limit($limit)
            ->get();

        // Offene Bestätigungen auch dann anzeigen, wenn sie älter sind als das Limit
        $fehlend = array_diff($offen, $posts->pluck('id')->all());
        if ($fehlend !== []) {
            $posts = $posts->concat(Post::query()->whereIn('id', $fehlend)->with(['author', 'groups', 'media'])->get());
        }

        return $this->mitStatus($posts, $user)
            ->sortBy(fn (Post $p) => [in_array($p->id, $offen, true) ? 0 : 1, -($p->datum()?->timestamp ?? 0)])
            ->values();
    }

    /**
     * Nachrichten einer Gruppe (aktiv oder Archiv), inkl. eigener Entwürfe für diese Gruppe.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function gruppenNachrichten(Group $group, User $user, bool $archiv = false, int $proSeite = 15)
    {
        $paginator = Post::query()
            ->whereHas('groups', fn (Builder $q) => $q->where('groups.id', $group->id))
            ->where(fn (Builder $q) => $q->where('released', true)
                ->orWhere('author_id', $user->id)
                ->when($user->can('manage posts'), fn (Builder $q) => $q->orWhereRaw('1 = 1')))
            ->where('archived', $archiv)
            ->with(['author', 'groups', 'media'])
            ->orderByDesc(DB::raw('COALESCE(veroeffentlicht_at, created_at)'))
            ->paginate($proSeite)
            ->withQueryString();

        $this->mitStatus($paginator->getCollection(), $user);

        return $paginator;
    }

    /**
     * Für die Themen-Seite: ungelesene oder unbestätigte Nachrichten der Gruppe (max. $limit).
     *
     * @return Collection<int, Post>
     */
    public function offeneFuerGruppe(Group $group, User $user, int $limit = 3): Collection
    {
        $offen = $this->offeneBestaetigungIds($user);
        $gelesen = PostLesestatus::query()->where('user_id', $user->id)->whereNotNull('gelesen_at')->pluck('post_id')->all();

        $posts = Post::query()
            ->veroeffentlicht()->aktiv()
            ->whereHas('groups', fn (Builder $q) => $q->where('groups.id', $group->id))
            ->where('author_id', '!=', $user->id)
            ->where(fn (Builder $q) => $q->whereIn('posts.id', $offen)
                ->orWhere(fn (Builder $q) => $q->whereNotIn('posts.id', $gelesen)
                    ->where('veroeffentlicht_at', '>=', now()->subDays(Post::NEU_TAGE))))
            ->with(['author', 'groups'])
            ->orderByDesc('veroeffentlicht_at')
            ->limit($limit)
            ->get();

        return $this->mitStatus($posts, $user);
    }

    /**
     * Zähler für Badges: ungelesene (letzte NEU_TAGE Tage) und unbestätigte Nachrichten.
     *
     * @return array{ungelesen: int, unbestaetigt: int}
     */
    public function zaehler(User $user): array
    {
        return [
            'ungelesen' => $this->ungelesenQuery($user)->count(),
            'unbestaetigt' => count($this->offeneBestaetigungIds($user)),
        ];
    }

    /**
     * Ungelesene Nachrichten je Gruppe (für die Seitenleiste, einmal pro Request).
     *
     * @return array<int, int> group_id => Anzahl
     */
    public function ungeleseneJeGruppe(User $user): array
    {
        if (isset($this->ungeleseneJeGruppeCache[$user->id])) {
            return $this->ungeleseneJeGruppeCache[$user->id];
        }

        $ids = $this->ungelesenQuery($user)->pluck('posts.id');

        $ergebnis = $ids->isEmpty() ? [] : DB::table('group_post')
            ->whereIn('post_id', $ids)
            ->select('group_id', DB::raw('COUNT(*) as anzahl'))
            ->groupBy('group_id')
            ->pluck('anzahl', 'group_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        return $this->ungeleseneJeGruppeCache[$user->id] = $ergebnis;
    }

    /**
     * IDs der Nachrichten, die die Person noch bestätigen muss.
     *
     * @return array<int, int>
     */
    public function offeneBestaetigungIds(User $user): array
    {
        $eigeneGruppen = DB::table('group_user')->where('user_id', $user->id)->pluck('group_id');

        return PostLesestatus::query()
            ->where('user_id', $user->id)
            ->whereNotNull('zugewiesen_at')
            ->whereNull('bestaetigt_at')
            ->whereHas('post', fn (Builder $q) => $q->veroeffentlicht()->aktiv()
                ->where('bestaetigung_erforderlich', true)
                ->where('author_id', '!=', $user->id)
                ->whereHas('groups', fn (Builder $g) => $g->whereIn('groups.id', $eigeneGruppen)))
            ->pluck('post_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Hängt den Lesestatus der Person als Attribut `mein_status` an.
     *
     * @param  Collection<int, Post>  $posts
     * @return Collection<int, Post>
     */
    public function mitStatus(Collection $posts, User $user): Collection
    {
        $status = PostLesestatus::query()
            ->where('user_id', $user->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->get()
            ->keyBy('post_id');

        $offen = $this->offeneBestaetigungIds($user);

        return $posts->each(function (Post $post) use ($status, $offen) {
            $post->setAttribute('mein_status', $status->get($post->id));
            $post->setAttribute('muss_bestaetigen', in_array($post->id, $offen, true));
        });
    }

    // ------------------------------------------------------------------
    // Intern
    // ------------------------------------------------------------------

    /** @return Builder<Post> */
    private function ungelesenQuery(User $user): Builder
    {
        $gruppen = $user->groups()->pluck('id')->all();

        return Post::query()
            ->veroeffentlicht()->aktiv()
            ->where('author_id', '!=', $user->id)
            ->where('veroeffentlicht_at', '>=', now()->subDays(Post::NEU_TAGE))
            ->whereHas('groups', fn (Builder $q) => $q->whereIn('groups.id', $gruppen))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('post_lesestatus')
                ->whereColumn('post_lesestatus.post_id', 'posts.id')
                ->where('post_lesestatus.user_id', $user->id)
                ->whereNotNull('post_lesestatus.gelesen_at'));
    }

    private function nachVeroeffentlichung(Post $post): void
    {
        $post->forceFill([
            'veroeffentlicht_at' => now(),
            'created_at' => now(),
            'bestaetigung_angefordert_at' => $post->bestaetigung_erforderlich ? now() : null,
        ])->save();

        $empfaenger = $this->empfaengerQuery($post)->get();

        if ($post->bestaetigung_erforderlich) {
            $this->zuweisen($post, $empfaenger, $post->bestaetigung_frist, false);
        }

        if ($empfaenger->isNotEmpty()) {
            Notification::send($empfaenger, new NachrichtVeroeffentlicht($post, NachrichtVeroeffentlicht::GRUND_NEU));
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function zuweisen(Post $post, Collection $users, ?Carbon $frist, bool $benachrichtigen, string $grund = NachrichtVeroeffentlicht::GRUND_NEU): void
    {
        foreach ($users as $user) {
            $status = PostLesestatus::query()->firstOrNew(['post_id' => $post->id, 'user_id' => $user->id]);
            $status->zugewiesen_at = now();
            $status->frist = $frist;
            $status->bestaetigt_at = null;
            $status->erinnert_vorab_at = null;
            $status->erinnert_frist_at = null;
            $status->save();
        }

        if ($benachrichtigen && $users->isNotEmpty()) {
            Notification::send($users, new NachrichtVeroeffentlicht($post, $grund));
        }
    }

    /** Wesentliche Änderung: alle Bestätigungen zurücksetzen und erneut anfordern. */
    private function bestaetigungenZuruecksetzen(Post $post): void
    {
        $post->forceFill(['bestaetigung_angefordert_at' => now(), 'manuell_erinnert_at' => null])->save();

        // Ausgeschiedene behalten ihre (alte) Bestätigung nicht – sie gilt für die alte Fassung.
        PostLesestatus::query()->where('post_id', $post->id)->update([
            'bestaetigt_at' => null,
            'zugewiesen_at' => null,
            'erinnert_vorab_at' => null,
            'erinnert_frist_at' => null,
        ]);

        $this->zuweisen($post, $this->empfaengerQuery($post)->get(), $post->bestaetigung_frist, true, NachrichtVeroeffentlicht::GRUND_GEAENDERT);
    }

    /** Neue Frist der Nachricht übernehmen (für alle ohne eigene, abweichende Frist). */
    private function fristAnpassen(Post $post, ?string $alteFrist): void
    {
        PostLesestatus::query()
            ->where('post_id', $post->id)
            ->whereNull('bestaetigt_at')
            ->when($alteFrist, fn ($q) => $q->whereDate('frist', $alteFrist), fn ($q) => $q->whereNull('frist'))
            ->update([
                'frist' => $post->bestaetigung_frist?->toDateString(),
                'erinnert_vorab_at' => null,
                'erinnert_frist_at' => null,
            ]);
    }

    /** Ursprüngliche Fristdauer ab heute, mindestens MINDESTFRIST_TAGE Tage. */
    private function fristFuerNachzuegler(Post $post): ?Carbon
    {
        if ($post->bestaetigung_frist === null) {
            return null;
        }

        $start = ($post->bestaetigung_angefordert_at ?? $post->veroeffentlicht_at ?? $post->created_at)->copy()->startOfDay();
        $dauer = max((int) $start->diffInDays($post->bestaetigung_frist, false), self::MINDESTFRIST_TAGE);

        return Carbon::today()->addDays($dauer);
    }

    /**
     * Prüft die Zielgruppen gegen die erlaubten Gruppen. Beim Bearbeiten bleiben bereits gesetzte
     * Gruppen erhalten, auch wenn die Person dort selbst nicht (mehr) posten dürfte.
     *
     * @param  array<int, int|string>  $gruppen
     * @return array<int, int>
     */
    private function pruefeGruppen(User $actor, array $gruppen, ?Post $post): array
    {
        $gruppen = array_values(array_unique(array_map('intval', $gruppen)));
        $erlaubt = $this->zielGruppen($actor)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $bisher = $post?->groups()->pluck('groups.id')->map(fn ($id) => (int) $id)->all() ?? [];

        $unerlaubt = array_diff($gruppen, $erlaubt, $bisher);

        if ($gruppen === [] || $unerlaubt !== []) {
            throw ValidationException::withMessages([
                'groups' => $gruppen === [] ? 'Bitte mindestens eine Gruppe wählen.' : 'In mindestens eine der gewählten Gruppen dürfen Sie nicht posten.',
            ]);
        }

        return $gruppen;
    }

    /**
     * @param  array<int, UploadedFile>  $dateien
     */
    private function dateienAnhaengen(Post $post, array $dateien): void
    {
        foreach ($dateien as $datei) {
            if (! $datei instanceof UploadedFile || ! $datei->isValid()) {
                continue;
            }

            $sammlung = str_starts_with((string) $datei->getMimeType(), 'image') ? 'images' : 'files';
            $post->addMedia($datei)->toMediaCollection($sammlung);
        }
    }
}
