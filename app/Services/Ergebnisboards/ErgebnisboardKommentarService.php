<?php

namespace App\Services\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisKommentar;
use App\Models\User;
use App\Notifications\ErgebnisKommentarNeu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kommentare auf Boards (karte_id = null) und an Karten.
 *
 * - Kommentieren darf, wer das Board lesen darf (nicht bei archivierten Boards).
 * - Eigene Kommentare bearbeiten/löschen; `verwalten` darf jeden Kommentar löschen.
 *   Gelöschte Kommentare bleiben als „Kommentar gelöscht“ (ohne Text) in der Liste.
 * - @-Erwähnungen: „@Vorname Nachname“ einer Person mit Leserecht (serverseitig erkannt).
 * - Benachrichtigt werden (ohne die schreibende Person, nur mit Leserecht):
 *   Karte → Zuständige + bisherige Kommentierende der Karte;
 *   Board → Ersteller:in + bisherige Kommentierende am Board; Erwähnte immer.
 *   Beim Bearbeiten nur neu hinzugekommene Erwähnungen, beim Löschen niemand.
 * - Kommentare erhöhen die Board-Version nicht (keine 409-Konflikte beim Bearbeiten).
 */
class ErgebnisboardKommentarService
{
    public function __construct(private ErgebnisboardService $boards)
    {
    }

    /** @return array<int, array> älteste zuerst */
    public function liste(Ergebnisboard $board, ?ErgebnisKarte $karte, User $user): array
    {
        $recht = $this->boards->rechtFuer($board, $user);

        return ErgebnisKommentar::withTrashed()
            ->where('board_id', $board->id)
            ->where('karte_id', $karte?->id)
            ->with('user:id,name')
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->map(fn (ErgebnisKommentar $k) => $this->darstellen($board, $k, $user, $recht))
            ->values()->all();
    }

    public function erstellen(Ergebnisboard $board, ?ErgebnisKarte $karte, User $user, string $text): ErgebnisKommentar
    {
        $text = $this->pruefen($text);
        $erwaehnt = $this->erwaehnungen($board, $text, $user);

        $kommentar = ErgebnisKommentar::create([
            'board_id' => $board->id,
            'karte_id' => $karte?->id,
            'user_id' => $user->id,
            'text' => $text,
            'erwaehnungen' => $erwaehnt ?: null,
        ]);

        $this->benachrichtigen($board, $kommentar, $user, $erwaehnt, true);

        return $kommentar;
    }

    public function bearbeiten(Ergebnisboard $board, ErgebnisKommentar $kommentar, User $user, string $text): ErgebnisKommentar
    {
        $text = $this->pruefen($text);
        if ($text === $kommentar->text) {
            return $kommentar;
        }
        $vorher = array_map('intval', $kommentar->erwaehnungen ?? []);
        $erwaehnt = $this->erwaehnungen($board, $text, $user);

        $kommentar->update([
            'text' => $text,
            'erwaehnungen' => $erwaehnt ?: null,
            'bearbeitet_at' => now(),
        ]);

        $neu = array_values(array_diff($erwaehnt, $vorher));
        if ($neu) {
            $this->benachrichtigen($board, $kommentar, $user, $neu, false);
        }

        return $kommentar;
    }

    public function loeschen(ErgebnisKommentar $kommentar): void
    {
        $kommentar->delete();
    }

    public function darfBearbeiten(Ergebnisboard $board, ErgebnisKommentar $kommentar, User $user): bool
    {
        return ! $kommentar->trashed() && ! $board->istArchiviert()
            && (int) $kommentar->user_id === (int) $user->id;
    }

    public function darfLoeschen(Ergebnisboard $board, ErgebnisKommentar $kommentar, User $user, ?string $recht = null): bool
    {
        if ($kommentar->trashed() || $board->istArchiviert()) {
            return false;
        }
        $recht ??= $this->boards->rechtFuer($board, $user);

        return (int) $kommentar->user_id === (int) $user->id || $recht === 'verwalten';
    }

    public function darstellen(Ergebnisboard $board, ErgebnisKommentar $k, User $user, ?string $recht = null): array
    {
        $geloescht = $k->trashed();
        $name = $k->user?->name ?? ($k->user_id ? 'Ehemalige Person' : 'Unbekannt');

        return [
            'id' => $k->id,
            'karte_id' => $k->karte_id,
            'person' => ['id' => $k->user_id, 'name' => $name, 'kuerzel' => $this->boards->kuerzel($name)],
            'text' => $geloescht ? null : $k->text,
            'html' => $geloescht ? null : $this->html($k->text, $k->erwaehnungen
                ? User::query()->whereIn('id', $k->erwaehnungen)->pluck('name')->sortByDesc(fn ($n) => mb_strlen($n))->all()
                : []),
            'erstellt' => $k->created_at?->toIso8601String(),
            'bearbeitet' => $k->bearbeitet_at?->toIso8601String(),
            'geloescht' => $geloescht ? $k->deleted_at?->toIso8601String() : null,
            'eigener' => (int) $k->user_id === (int) $user->id,
            'darf_bearbeiten' => $this->darfBearbeiten($board, $k, $user),
            'darf_loeschen' => $this->darfLoeschen($board, $k, $user, $recht ?? $this->boards->rechtFuer($board, $user)),
        ];
    }

    /** Anzahl sichtbarer (nicht gelöschter) Kommentare – Board oder Karte. */
    public function anzahl(Ergebnisboard $board, ?ErgebnisKarte $karte): int
    {
        return ErgebnisKommentar::query()->where('board_id', $board->id)->where('karte_id', $karte?->id)->count();
    }

    /**
     * Reiner Text → HTML: escapen, URLs verlinken, @-Erwähnungen hervorheben, Zeilenumbrüche
     * bleiben über CSS (white-space: pre-line).
     */
    /** @param  array<int, string>  $namen  Namen der erwähnten Personen */
    public function html(string $text, array $namen = []): string
    {
        $html = e($text);
        $html = preg_replace_callback(
            '~\bhttps?://[^\s<>"\']+~iu',
            function ($m) {
                $url = rtrim($m[0], '.,;:!?)');
                $rest = substr($m[0], strlen($url));

                return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$url.'</a>'.$rest;
            },
            $html
        );

        foreach ($namen as $name) {
            $html = preg_replace('~@'.preg_quote(e($name), '~').'(?![\p{L}\p{N}])~iu', '<span class="erg-erwaehnung">$0</span>', $html) ?? $html;
        }

        return $html;
    }

    // ------------------------------------------------------------------
    // intern
    // ------------------------------------------------------------------

    private function pruefen(string $text): string
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') {
            throw ValidationException::withMessages(['text' => 'Bitte einen Kommentar eingeben.']);
        }
        if (mb_strlen($text) > ErgebnisKommentar::MAX_ZEICHEN) {
            throw ValidationException::withMessages(['text' => 'Ein Kommentar darf höchstens '.ErgebnisKommentar::MAX_ZEICHEN.' Zeichen haben.']);
        }

        return $text;
    }

    /**
     * IDs der erwähnten Personen (nur mit Leserecht, ohne die schreibende Person).
     * Erkannt wird „@Name“ mit dem vollständigen Namen; längere Namen zuerst, damit
     * „@Anna Maria Muster“ nicht zusätzlich „@Anna Maria“ auslöst.
     *
     * @return array<int, int>
     */
    public function erwaehnungen(Ergebnisboard $board, string $text, User $autor): array
    {
        if (! str_contains($text, '@')) {
            return [];
        }
        $rest = mb_strtolower($text);
        $ids = [];
        $personen = $this->boards->personenMitZugriff($board)
            ->sortByDesc(fn (User $u) => mb_strlen($u->name));

        foreach ($personen as $person) {
            $marke = '@'.mb_strtolower($person->name);
            $pos = mb_strpos($rest, $marke);
            if ($pos === false) {
                continue;
            }
            $danach = mb_substr($rest, $pos + mb_strlen($marke), 1);
            if ($danach !== '' && preg_match('/[\p{L}\p{N}]/u', $danach)) {
                continue; // nur ganze Namen
            }
            $rest = str_replace($marke, ' ', $rest);
            if ((int) $person->id !== (int) $autor->id) {
                $ids[] = (int) $person->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, int>  $erwaehnt
     */
    private function benachrichtigen(Ergebnisboard $board, ErgebnisKommentar $kommentar, User $autor, array $erwaehnt, bool $neu): void
    {
        $empfaenger = collect($erwaehnt);
        $kommentar->loadMissing('karte');

        if ($neu) {
            $beteiligte = $kommentar->karte
                ? $kommentar->karte->zustaendige()->pluck('users.id')
                : collect([$board->created_by]);
            $bisher = ErgebnisKommentar::query()
                ->where('board_id', $board->id)
                ->where('karte_id', $kommentar->karte_id)
                ->whereKeyNot($kommentar->id)
                ->pluck('user_id');
            $empfaenger = $empfaenger->merge($beteiligte)->merge($bisher);
        }

        $ids = $empfaenger->filter()->map(fn ($id) => (int) $id)->unique()
            ->reject(fn ($id) => $id === (int) $autor->id)->values();
        if ($ids->isEmpty()) {
            return;
        }

        /** @var Collection<int, User> $personen */
        $personen = User::query()->whereIn('id', $ids)->get()
            ->filter(fn (User $u) => $this->boards->darf($board, $u, 'lesen'));

        [$genannt, $andere] = $personen->partition(fn (User $u) => in_array((int) $u->id, $erwaehnt, true));
        $vorschau = Str::limit(preg_replace('/\s+/u', ' ', $kommentar->text), 140);

        if ($genannt->isNotEmpty()) {
            Notification::send($genannt, new ErgebnisKommentarNeu($kommentar, $autor->name, $vorschau, true));
        }
        if ($andere->isNotEmpty()) {
            Notification::send($andere, new ErgebnisKommentarNeu($kommentar, $autor->name, $vorschau, false));
        }
    }
}
