<?php

namespace App\Services\Wiki;

use App\Exceptions\VersionKonfliktException;
use App\Models\Group;
use App\Models\User;
use App\Models\WikiEinschraenkung;
use App\Models\WikiLink;
use App\Models\WikiRevision;
use App\Models\WikiSeite;
use App\Models\WikiTag;
use App\Models\WikiWeiterleitung;
use App\Support\WikiHtml;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Alle schreibenden Regeln des Wikis: Anlegen/Speichern (Revision, Bereinigung, base64-Bilder, Slug + Weiterleitung,
 * optimistische Sperre über `version`), Wiederherstellen, Papierkorb, Schutz, Einschränkung, Struktur/Sortierung,
 * Tags, Favoriten, Links (Backlinks/rote Links), Systemseiten und der Hinweis „bearbeitet gerade“.
 */
class WikiService
{
    /** URL-Segmente unter /wiki, die keine Seiten-Slugs sein dürfen. */
    public const RESERVIERT = [
        'neu', 'suche', 'alle', 'aenderungen', 'papierkorb', 'struktur', 'tags', 'tag', 'anhang', 'seiten',
        'favoriten', 'vorschau', 'create', 'all', 'search', 'add', 'links', 'bearbeiten',
    ];

    public const MAX_TAGS = 20;

    private const BEARBEITUNG_SEKUNDEN = 90;

    public function __construct(private WikiAnhangService $anhaenge)
    {
    }

    // ------------------------------------------------------------------
    // Finden, Slugs
    // ------------------------------------------------------------------

    /**
     * Seite zu einem Slug – auch über Weiterleitungen (alte Slugs/Titel-URLs) und im Papierkorb.
     *
     * @return array{0: ?WikiSeite, 1: bool}  [Seite, kam über eine Weiterleitung]
     */
    public function finde(string $slug): array
    {
        $slug = trim($slug);
        $klein = mb_strtolower($slug);

        $seite = WikiSeite::withTrashed()->where('slug', $klein)->first()
            ?? ($klein !== $slug ? WikiSeite::withTrashed()->where('slug', $slug)->first() : null);
        if ($seite) {
            return [$seite, false];
        }

        $weiterleitung = WikiWeiterleitung::where('alter_slug', $slug)->first()
            ?? WikiWeiterleitung::where('alter_slug', $klein)->first();
        if ($weiterleitung && ($ziel = WikiSeite::withTrashed()->find($weiterleitung->seite_id))) {
            return [$ziel, true];
        }

        return [null, false];
    }

    public function slugFuer(string $titel, ?int $parentId = null, ?int $ignoriereId = null): string
    {
        $basis = Str::limit(Str::slug($titel, '-', 'de'), 150, '') ?: 'seite';
        if (in_array($basis, self::RESERVIERT, true)) {
            $basis .= '-seite';
        }

        $kandidaten = [$basis];
        if ($parentId && ($eltern = WikiSeite::withTrashed()->find($parentId)) && ! $eltern->istStartseite()) {
            $kandidaten[] = Str::limit($eltern->slug.'-'.$basis, 170, '');
        }

        foreach ($kandidaten as $kandidat) {
            if ($this->slugFrei($kandidat, $ignoriereId)) {
                return $kandidat;
            }
        }

        $n = 2;
        while (! $this->slugFrei($basis.'-'.$n, $ignoriereId)) {
            $n++;
        }

        return $basis.'-'.$n;
    }

    private function slugFrei(string $slug, ?int $ignoriereId): bool
    {
        $seite = WikiSeite::withTrashed()->where('slug', $slug)->when($ignoriereId, fn ($q) => $q->where('id', '!=', $ignoriereId))->exists();
        $weiterleitung = WikiWeiterleitung::where('alter_slug', $slug)->when($ignoriereId, fn ($q) => $q->where('seite_id', '!=', $ignoriereId))->exists();

        return ! $seite && ! $weiterleitung;
    }

    // ------------------------------------------------------------------
    // Anlegen, Speichern, Wiederherstellen
    // ------------------------------------------------------------------

    /**
     * @param  array{titel: string, inhalt?: ?string, parent_id?: ?int, notiz?: ?string, tags?: array}  $daten
     */
    public function anlegen(User $user, array $daten): WikiSeite
    {
        $parentId = $this->pruefeEltern(null, $daten['parent_id'] ?? null);

        $seite = DB::transaction(function () use ($user, $daten, $parentId) {
            $seite = new WikiSeite([
                'titel' => $this->titel($daten['titel']),
                'parent_id' => $parentId,
            ]);
            $seite->slug = $this->slugFuer($seite->titel, $parentId);
            $seite->erstellt_von = $user->id;
            $seite->geaendert_von = $user->id;
            $seite->bearbeitet_at = now();
            $seite->version = 1;
            $seite->save();

            $seite->inhalt = $this->inhaltVorbereiten($seite, $daten['inhalt'] ?? '', $user);
            $seite->inhalt_text = WikiHtml::klartext($seite->inhalt);
            $seite->save();

            $this->revision($seite, $user, $daten['notiz'] ?? null);

            if (array_key_exists('tags', $daten)) {
                $this->tagsSetzen($seite, (array) $daten['tags']);
            }
            $this->linksAktualisieren($seite);
            $this->offeneLinksAnbinden($seite);

            return $seite;
        });

        WikiZugriff::zuruecksetzen();

        return $seite;
    }

    /**
     * @param  array{titel?: string, inhalt?: ?string, parent_id?: ?int, notiz?: ?string, tags?: array, wiederhergestellt_aus?: int}  $daten
     *
     * @throws VersionKonfliktException wenn zwischenzeitlich jemand anderes gespeichert hat
     */
    public function speichern(WikiSeite $seite, User $user, array $daten, int $version): WikiSeite
    {
        $seite = DB::transaction(function () use ($seite, $user, $daten, $version) {
            /** @var WikiSeite $frisch */
            $frisch = WikiSeite::query()->lockForUpdate()->findOrFail($seite->id);
            if ($frisch->version !== $version) {
                throw new VersionKonfliktException('Die Seite wurde zwischenzeitlich geändert.');
            }

            $titel = array_key_exists('titel', $daten) && ! $frisch->istStartseite() ? $this->titel($daten['titel']) : $frisch->titel;
            $inhalt = array_key_exists('inhalt', $daten) ? $this->inhaltVorbereiten($frisch, $daten['inhalt'], $user) : $frisch->inhalt;
            $parentId = array_key_exists('parent_id', $daten) && ! $frisch->istStartseite()
                ? $this->pruefeEltern($frisch, $daten['parent_id'])
                : $frisch->parent_id;

            $inhaltGeaendert = $titel !== $frisch->titel || (string) $inhalt !== (string) $frisch->inhalt;
            $elternGeaendert = $parentId !== $frisch->parent_id;
            $tagsGeaendert = array_key_exists('tags', $daten) && $this->tagsSetzen($frisch, (array) $daten['tags']);

            if ($titel !== $frisch->titel && ! $frisch->istStartseite() && ! $frisch->systemseite) {
                $neu = $this->slugFuer($titel, $parentId, $frisch->id);
                if ($neu !== $frisch->slug) {
                    $alt = $frisch->slug;
                    $frisch->slug = $neu;
                    WikiWeiterleitung::where('alter_slug', $neu)->delete();
                    $this->weiterleitungAnlegen($alt, $frisch);
                }
            }

            $frisch->titel = $titel;
            $frisch->parent_id = $parentId;
            if ($elternGeaendert) {
                $frisch->sortierung = null;
            }

            if ($inhaltGeaendert) {
                $frisch->inhalt = $inhalt;
                $frisch->inhalt_text = WikiHtml::klartext($inhalt);
                $frisch->geaendert_von = $user->id;
                $frisch->bearbeitet_at = now();
            }

            if ($inhaltGeaendert || $elternGeaendert || $tagsGeaendert) {
                $frisch->version++;
            }
            $frisch->save();

            if ($inhaltGeaendert) {
                $this->revision($frisch, $user, $daten['notiz'] ?? null, $daten['wiederhergestellt_aus'] ?? null);
                $this->linksAktualisieren($frisch);
                $this->offeneLinksAnbinden($frisch);
            }

            return $frisch;
        });

        WikiZugriff::zuruecksetzen();
        $this->bearbeitungBeenden($seite, $user);

        return $seite;
    }

    public function wiederherstellen(WikiSeite $seite, WikiRevision $revision, User $user): WikiSeite
    {
        abort_unless($revision->seite_id === $seite->id, 404);

        return $this->speichern($seite, $user, [
            'titel' => $revision->titel,
            'inhalt' => $revision->inhalt,
            'notiz' => 'Fassung vom '.$revision->created_at?->format('d.m.Y H:i').' wiederhergestellt',
            'wiederhergestellt_aus' => $revision->id,
        ], $seite->version);
    }

    // ------------------------------------------------------------------
    // Papierkorb, Schutz, Einschränkung, Struktur
    // ------------------------------------------------------------------

    public function loeschen(WikiSeite $seite, User $user): void
    {
        if ($seite->istStartseite() || $seite->systemseite) {
            throw ValidationException::withMessages(['seite' => 'Start- und Systemseiten können nicht gelöscht werden.']);
        }
        if ($seite->kinder()->exists()) {
            throw ValidationException::withMessages(['seite' => 'Die Seite hat Unterseiten. Bitte diese zuerst verschieben oder löschen.']);
        }

        $seite->geloescht_von = $user->id;
        $seite->save();
        $seite->delete();
        WikiZugriff::zuruecksetzen();
    }

    public function ausPapierkorbHolen(WikiSeite $seite): void
    {
        if ($seite->parent_id && ! WikiSeite::whereKey($seite->parent_id)->exists()) {
            $seite->parent_id = null;
            $seite->sortierung = null;
        }
        $seite->geloescht_von = null;
        $seite->restore();
        WikiZugriff::zuruecksetzen();
    }

    public function schuetzen(WikiSeite $seite, bool $geschuetzt): void
    {
        $seite->geschuetzt = $geschuetzt;
        $seite->save();
    }

    /**
     * @param  int[]  $rollenIds
     * @param  int[]  $gruppenIds
     */
    public function einschraenken(WikiSeite $seite, array $rollenIds, array $gruppenIds): void
    {
        $rollenIds = Role::whereIn('id', $rollenIds)->pluck('id')->all();
        $gruppenIds = Group::whereIn('id', $gruppenIds)->pluck('id')->all();

        DB::transaction(function () use ($seite, $rollenIds, $gruppenIds) {
            $seite->einschraenkungen()->delete();
            foreach ([Role::class => $rollenIds, Group::class => $gruppenIds] as $typ => $ids) {
                foreach ($ids as $id) {
                    WikiEinschraenkung::create(['seite_id' => $seite->id, 'berechtigter_type' => $typ, 'berechtigter_id' => $id]);
                }
            }
        });

        WikiZugriff::zuruecksetzen();
    }

    /**
     * Neue Struktur aus dem Baum-Editor: [{id, parent_id, sortierung}, …] – nur sichtbare Seiten, keine Zyklen.
     *
     * @param  array<int, array{id: int, parent_id: ?int, sortierung: int}>  $knoten
     */
    public function strukturSpeichern(array $knoten): void
    {
        $eltern = WikiSeite::pluck('parent_id', 'id')->map(fn ($p) => $p === null ? null : (int) $p)->all();
        $startId = WikiSeite::where('slug', WikiSeite::START_SLUG)->value('id');

        foreach ($knoten as $k) {
            $id = (int) $k['id'];
            $parent = isset($k['parent_id']) && $k['parent_id'] !== null && $k['parent_id'] !== '' ? (int) $k['parent_id'] : null;
            $istStart = $startId !== null && ($id === (int) $startId || $parent === (int) $startId);
            if (! array_key_exists($id, $eltern) || ($parent !== null && ! array_key_exists($parent, $eltern)) || $istStart) {
                throw ValidationException::withMessages(['struktur' => 'Unbekannte Seite in der Struktur.']);
            }
            $eltern[$id] = $parent;
        }

        foreach (array_keys($eltern) as $id) {
            $schritte = 0;
            $aktuell = $eltern[$id];
            while ($aktuell !== null) {
                if ($aktuell === $id || ++$schritte > 100) {
                    throw ValidationException::withMessages(['struktur' => 'Eine Seite kann nicht unter sich selbst liegen.']);
                }
                $aktuell = $eltern[$aktuell] ?? null;
            }
        }

        DB::transaction(function () use ($knoten) {
            foreach ($knoten as $k) {
                $parent = isset($k['parent_id']) && $k['parent_id'] !== '' ? $k['parent_id'] : null;
                WikiSeite::whereKey((int) $k['id'])->update([
                    'parent_id' => $parent === null ? null : (int) $parent,
                    'sortierung' => max(0, (int) $k['sortierung']),
                ]);
            }
        });

        WikiZugriff::zuruecksetzen();
    }

    public function alphabetischSortieren(): void
    {
        WikiSeite::query()->update(['sortierung' => null]);
    }

    // ------------------------------------------------------------------
    // Tags
    // ------------------------------------------------------------------

    /**
     * @param  string[]  $namen
     * @return bool ob sich die Tags geändert haben
     */
    public function tagsSetzen(WikiSeite $seite, array $namen): bool
    {
        $ids = collect($namen)
            ->map(fn ($n) => Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $n)), 40, ''))
            ->filter(fn ($n) => $n !== '')
            ->unique(fn ($n) => mb_strtolower($n))
            ->take(self::MAX_TAGS)
            ->map(fn ($n) => $this->tag($n)->id)
            ->values()
            ->all();

        $vorher = $seite->tags()->pluck('wiki_tags.id')->sort()->values()->all();
        $seite->tags()->sync($ids);

        return $vorher !== collect($ids)->sort()->values()->all();
    }

    public function tag(string $name): WikiTag
    {
        $slug = Str::slug($name, '-', 'de') ?: Str::lower(Str::random(6));

        return WikiTag::firstOrCreate(['slug' => $slug], ['name' => $name]);
    }

    /** Umbenennen; trifft der neue Name einen vorhandenen Tag, werden beide zusammengeführt. */
    public function tagUmbenennen(WikiTag $tag, string $name): WikiTag
    {
        $name = Str::limit(trim($name), 40, '');
        $slug = Str::slug($name, '-', 'de');
        if ($name === '' || $slug === '') {
            throw ValidationException::withMessages(['name' => 'Bitte einen Namen angeben.']);
        }

        $ziel = WikiTag::where('slug', $slug)->where('id', '!=', $tag->id)->first();
        if ($ziel) {
            $this->tagZusammenfuehren($tag, $ziel);

            return $ziel;
        }

        $tag->update(['name' => $name, 'slug' => $slug]);

        return $tag;
    }

    public function tagZusammenfuehren(WikiTag $quelle, WikiTag $ziel): void
    {
        if ($quelle->id === $ziel->id) {
            return;
        }
        DB::transaction(function () use ($quelle, $ziel) {
            $seiten = DB::table('wiki_seite_tag')->where('tag_id', $quelle->id)->pluck('seite_id');
            foreach ($seiten as $seiteId) {
                DB::table('wiki_seite_tag')->insertOrIgnore(['seite_id' => $seiteId, 'tag_id' => $ziel->id]);
            }
            $quelle->delete();
        });
    }

    // ------------------------------------------------------------------
    // Favoriten, Bearbeitungshinweis
    // ------------------------------------------------------------------

    public function favoritUmschalten(WikiSeite $seite, User $user): bool
    {
        $vorhanden = DB::table('wiki_favoriten')->where('user_id', $user->id)->where('seite_id', $seite->id);
        if ($vorhanden->exists()) {
            $vorhanden->delete();

            return false;
        }
        DB::table('wiki_favoriten')->insert(['user_id' => $user->id, 'seite_id' => $seite->id, 'created_at' => now()]);

        return true;
    }

    public function istFavorit(WikiSeite $seite, User $user): bool
    {
        return DB::table('wiki_favoriten')->where('user_id', $user->id)->where('seite_id', $seite->id)->exists();
    }

    /**
     * Meldet, dass die Person die Seite gerade bearbeitet, und liefert die Namen der anderen Bearbeitenden.
     *
     * @return string[]
     */
    public function bearbeitungMelden(WikiSeite $seite, User $user): array
    {
        $schluessel = 'wiki-bearbeitung-'.$seite->id;
        $liste = collect(Cache::get($schluessel, []))
            ->filter(fn ($e) => ($e['zeit'] ?? 0) > now()->subSeconds(self::BEARBEITUNG_SEKUNDEN)->timestamp);
        $liste[$user->id] = ['name' => $user->name, 'zeit' => now()->timestamp];
        Cache::put($schluessel, $liste->all(), now()->addSeconds(self::BEARBEITUNG_SEKUNDEN * 2));

        return $liste->except($user->id)->pluck('name')->values()->all();
    }

    public function bearbeitungBeenden(WikiSeite $seite, User $user): void
    {
        $schluessel = 'wiki-bearbeitung-'.$seite->id;
        $liste = Cache::get($schluessel, []);
        unset($liste[$user->id]);
        Cache::put($schluessel, $liste, now()->addSeconds(self::BEARBEITUNG_SEKUNDEN * 2));
    }

    // ------------------------------------------------------------------
    // Links
    // ------------------------------------------------------------------

    public function linksAktualisieren(WikiSeite $seite): void
    {
        $slugs = collect(WikiHtml::interneSlugs($seite->inhalt))
            ->map(fn ($s) => Str::limit($s, 190, ''))
            ->unique()
            ->values();

        WikiLink::where('von_seite_id', $seite->id)->whereNotIn('ziel_slug', $slugs->all())->delete();
        foreach ($slugs as $slug) {
            WikiLink::updateOrCreate(
                ['von_seite_id' => $seite->id, 'ziel_slug' => $slug],
                ['ziel_seite_id' => $this->zielId($slug)]
            );
        }
    }

    public function offeneLinksAnbinden(WikiSeite $seite): void
    {
        WikiLink::whereNull('ziel_seite_id')->whereIn('ziel_slug', [$seite->slug])->update(['ziel_seite_id' => $seite->id]);
    }

    private function zielId(string $slug): ?int
    {
        [$seite] = $this->finde($slug);

        return $seite?->id;
    }

    // ------------------------------------------------------------------
    // Lesen: Baum, Pfad, Sichtbarkeit
    // ------------------------------------------------------------------

    /**
     * Seitenbaum der sichtbaren Seiten (ohne Startseite), sortiert nach `sortierung`, sonst alphabetisch.
     *
     * @return Collection<int, array{id: int, titel: string, slug: string, url: string, geschuetzt: bool, eingeschraenkt: bool, kinder: array}>
     */
    public function baum(User $user): Collection
    {
        $sichtbar = WikiZugriff::sichtbareIds($user);
        $eingeschraenkt = DB::table('wiki_einschraenkungen')->distinct()->pluck('seite_id')->flip();

        $seiten = WikiSeite::query()
            ->where('slug', '!=', WikiSeite::START_SLUG)
            ->get(['id', 'titel', 'slug', 'parent_id', 'sortierung', 'geschuetzt', 'systemseite'])
            ->filter(fn (WikiSeite $s) => isset($sichtbar[$s->id]));

        $nachEltern = $seiten->groupBy(fn (WikiSeite $s) => $s->parent_id ?? 0);

        $bauen = function (int $parent, int $tiefe = 0) use (&$bauen, $nachEltern, $eingeschraenkt): array {
            if ($tiefe > 30) {
                return [];
            }

            return collect($nachEltern->get($parent, []))
                ->sort(fn (WikiSeite $a, WikiSeite $b) => [$a->sortierung ?? PHP_INT_MAX, self::sortTitel($a->titel)] <=> [$b->sortierung ?? PHP_INT_MAX, self::sortTitel($b->titel)])
                ->map(fn (WikiSeite $s) => [
                    'id' => $s->id,
                    'titel' => $s->titel,
                    'slug' => $s->slug,
                    'url' => route('wiki.show', $s->slug),
                    'geschuetzt' => $s->istGeschuetzt(),
                    'eingeschraenkt' => isset($eingeschraenkt[$s->id]),
                    'kinder' => $bauen($s->id, $tiefe + 1),
                ])
                ->values()
                ->all();
        };

        $startId = WikiSeite::where('slug', WikiSeite::START_SLUG)->value('id');
        $wurzeln = $bauen(0);
        if ($startId) {
            // Unterseiten der Startseite erscheinen auf oberster Ebene
            $wurzeln = array_merge($wurzeln, $bauen((int) $startId));
        }

        return collect($wurzeln);
    }

    /** Übergeordnete Seiten von oben nach unten (für Brotkrumen). */
    public function pfad(WikiSeite $seite): Collection
    {
        $pfad = collect();
        $aktuell = $seite->parent_id ? WikiSeite::find($seite->parent_id) : null;
        $tiefe = 0;
        while ($aktuell && $tiefe++ < 30) {
            if (! $aktuell->istStartseite()) {
                $pfad->prepend($aktuell);
            }
            $aktuell = $aktuell->parent_id ? WikiSeite::find($aktuell->parent_id) : null;
        }

        return $pfad;
    }

    /** Mögliche Elternseiten (sichtbar, nicht die Seite selbst oder darunter) als [id => „Pfad › Titel“]. */
    public function elternOptionen(User $user, ?WikiSeite $seite = null): array
    {
        $ausgeschlossen = $seite ? $this->nachfahrenIds($seite)->push($seite->id)->flip() : collect();
        $optionen = [];
        $durchlaufen = function (array $knoten, string $praefix) use (&$durchlaufen, &$optionen, $ausgeschlossen) {
            foreach ($knoten as $k) {
                if (isset($ausgeschlossen[$k['id']])) {
                    continue;
                }
                $optionen[$k['id']] = $praefix.$k['titel'];
                $durchlaufen($k['kinder'], $praefix.$k['titel'].' › ');
            }
        };
        $durchlaufen($this->baum($user)->all(), '');

        return $optionen;
    }

    public function nachfahrenIds(WikiSeite $seite): Collection
    {
        $eltern = WikiSeite::withTrashed()->pluck('parent_id', 'id');
        $ergebnis = collect();
        $offen = [$seite->id];
        while ($offen) {
            $id = array_shift($offen);
            foreach ($eltern as $kind => $parent) {
                if ((int) $parent === (int) $id && ! $ergebnis->contains($kind)) {
                    $ergebnis->push((int) $kind);
                    $offen[] = (int) $kind;
                }
            }
        }

        return $ergebnis;
    }

    /**
     * Status eines Link-Ziels für die Anzeige: ok | fehlt | gesperrt.
     *
     * @return callable(string): string
     */
    public function linkPruefer(User $user): callable
    {
        $sichtbar = WikiZugriff::sichtbareIds($user);
        $merker = [];

        return function (string $slug) use ($sichtbar, &$merker): string {
            if (! isset($merker[$slug])) {
                [$seite] = $this->finde($slug);
                $merker[$slug] = match (true) {
                    $seite === null || $seite->trashed() => 'fehlt',
                    isset($sichtbar[$seite->id]) => 'ok',
                    default => 'gesperrt',
                };
            }

            return $merker[$slug];
        };
    }

    // ------------------------------------------------------------------
    // Systemseiten (Handbücher der Module, eingespielt über Migrationen)
    // ------------------------------------------------------------------

    /**
     * Legt eine Systemseite an oder spielt eine neue Fassung als Revision ein (ohne Versionsprüfung).
     * Systemseiten sind geschützt; lokale Änderungen werden beim nächsten Einspielen überschrieben.
     */
    public function systemseite(string $slug, string $titel, string $html, ?string $elternSlug = null, ?string $notiz = null): WikiSeite
    {
        $html = WikiHtml::bereinigen($html);
        $seite = WikiSeite::withTrashed()->where('slug', $slug)->first();
        if ($seite?->trashed()) {
            $seite->restore();
        }

        $parentId = $elternSlug ? WikiSeite::where('slug', $elternSlug)->value('id') : null;

        if (! $seite) {
            $seite = new WikiSeite(['titel' => $this->titel($titel), 'parent_id' => $parentId]);
            $seite->slug = $slug;
            $seite->systemseite = true;
            $seite->version = 1;
            $seite->bearbeitet_at = now();
            $seite->inhalt = $html;
            $seite->inhalt_text = WikiHtml::klartext($html);
            $seite->save();
            $this->revision($seite, null, $notiz ?? 'Systemseite angelegt');
        } elseif ($seite->inhalt !== $html || $seite->titel !== $this->titel($titel)) {
            $seite->titel = $this->titel($titel);
            $seite->systemseite = true;
            $seite->inhalt = $html;
            $seite->inhalt_text = WikiHtml::klartext($html);
            $seite->geaendert_von = null;
            $seite->bearbeitet_at = now();
            $seite->version++;
            $seite->save();
            $this->revision($seite, null, $notiz ?? 'Systemseite aktualisiert');
        } elseif (! $seite->systemseite) {
            $seite->systemseite = true;
            $seite->save();
        }

        $this->linksAktualisieren($seite);
        $this->offeneLinksAnbinden($seite);
        WikiZugriff::zuruecksetzen();

        return $seite;
    }

    // ------------------------------------------------------------------
    // intern
    // ------------------------------------------------------------------

    public function weiterleitungAnlegen(string $alterSlug, WikiSeite $seite): void
    {
        if ($alterSlug === '' || $alterSlug === $seite->slug) {
            return;
        }
        WikiWeiterleitung::updateOrCreate(['alter_slug' => Str::limit($alterSlug, 190, '')], ['seite_id' => $seite->id]);
    }

    private function revision(WikiSeite $seite, ?User $user, ?string $notiz, ?int $quelle = null, ?Carbon $zeit = null): WikiRevision
    {
        return WikiRevision::create([
            'seite_id' => $seite->id,
            'titel' => $seite->titel,
            'inhalt' => $seite->inhalt,
            'notiz' => filled($notiz) ? Str::limit(trim($notiz), 250) : null,
            'user_id' => $user?->id,
            'wiederhergestellt_aus' => $quelle,
            'created_at' => $zeit ?? now(),
        ]);
    }

    private function inhaltVorbereiten(WikiSeite $seite, ?string $html, ?User $user): string
    {
        return WikiHtml::bereinigen($this->anhaenge->base64Umwandeln($seite, (string) $html, $user));
    }

    private function titel(string $titel): string
    {
        $titel = trim(preg_replace('/\s+/u', ' ', $titel));
        if ($titel === '') {
            throw ValidationException::withMessages(['titel' => 'Bitte einen Titel angeben.']);
        }

        return Str::limit($titel, 150, '');
    }

    private function pruefeEltern(?WikiSeite $seite, mixed $parentId): ?int
    {
        if ($parentId === null || $parentId === '' || (int) $parentId === 0) {
            return null;
        }
        $parentId = (int) $parentId;
        $eltern = WikiSeite::find($parentId);
        if (! $eltern) {
            throw ValidationException::withMessages(['parent_id' => 'Die übergeordnete Seite gibt es nicht.']);
        }
        if ($eltern->istStartseite()) {
            return null;
        }
        if ($seite && ($parentId === $seite->id || $this->nachfahrenIds($seite)->contains($parentId))) {
            throw ValidationException::withMessages(['parent_id' => 'Eine Seite kann nicht unter sich selbst liegen.']);
        }

        return $parentId;
    }

    public static function sortTitel(string $titel): string
    {
        return mb_strtolower(Str::ascii($titel));
    }
}
