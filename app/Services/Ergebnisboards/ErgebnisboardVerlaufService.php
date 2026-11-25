<?php

namespace App\Services\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisVerlauf;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Änderungsverlauf der Boards: fachliche Einträge („verschob Karte X von A nach B“) statt
 * roher Modell-Diffs. Geschrieben aus ErgebnisboardService/ErgebnisboardAufgabenService,
 * gelesen über liste() (Seitenleiste „Verlauf“ und Karten-Dialog).
 *
 * Nicht erfasst: Umsortieren innerhalb eines Feldes und Kommentare.
 * Details enthalten immer den Kartentitel, damit Einträge auch nach dem endgültigen
 * Löschen einer Karte lesbar bleiben.
 */
class ErgebnisboardVerlaufService
{
    public const SEITE = 50;

    /** Lesbare Namen der Kopf-/Board-Felder. */
    public const FELDNAMEN = [
        'titel' => 'Titel',
        'untertitel' => 'Untertitel',
        'kopfzeile' => 'Stand / Gremium',
        'fusszeile' => 'Fußzeile',
        'farbschema' => 'Farbschema',
        'meeting' => 'Sitzung',
        'theme' => 'Thema',
        'meeting_lesen' => 'Lesezugriff für Sitzungsteilnehmende',
        'achsen' => 'Achsenbeschriftung',
        'legende' => 'Legende',
        // Karten
        'zusatz' => 'Zusatz',
        'etikett' => 'Etikett',
        'fokus' => 'Arbeitsschwerpunkt',
        'breit' => 'Breite Darstellung',
        'faellig_am' => 'Fälligkeit',
    ];

    public const RECHTE = ['lesen' => 'lesen', 'schreiben' => 'schreiben', 'verwalten' => 'verwalten'];

    public function eintragen(Ergebnisboard $board, string $aktion, array $details = [], ?ErgebnisKarte $karte = null, ?User $user = null): ErgebnisVerlauf
    {
        return ErgebnisVerlauf::create([
            'board_id' => $board->id,
            'karte_id' => $karte?->id,
            'user_id' => $user?->id ?? Auth::id(),
            'aktion' => $aktion,
            'details' => $details ?: null,
        ]);
    }

    /** Kartenbezogener Eintrag – der Titel der Karte steht immer in den Details. */
    public function karte(Ergebnisboard $board, ErgebnisKarte $karte, string $aktion, array $details = [], ?User $user = null): ErgebnisVerlauf
    {
        return $this->eintragen($board, $aktion, ['karte' => $karte->titel] + $details, $karte, $user);
    }

    /**
     * Seite des Verlaufs (neueste zuerst). $filter: alle|board|karten; $karteId nur diese Karte;
     * $vor = ID des letzten bereits geladenen Eintrags.
     *
     * @return array{eintraege: array<int, array>, mehr: bool}
     */
    public function liste(Ergebnisboard $board, string $filter = 'alle', ?int $karteId = null, ?int $vor = null): array
    {
        $eintraege = $board->verlauf()
            ->with('user:id,name')
            ->when($karteId, fn ($q) => $q->where('karte_id', $karteId))
            ->when(! $karteId && $filter === 'board', fn ($q) => $q->whereNull('karte_id'))
            ->when(! $karteId && $filter === 'karten', fn ($q) => $q->whereNotNull('karte_id'))
            ->when($vor, fn ($q) => $q->where('id', '<', $vor))
            ->orderByDesc('id')
            ->limit(self::SEITE + 1)
            ->get();

        $mehr = $eintraege->count() > self::SEITE;
        $eintraege = $eintraege->take(self::SEITE);

        // Gelöschte Karten, die noch wiederhergestellt werden können
        $geloescht = ErgebnisKarte::onlyTrashed()->where('board_id', $board->id)
            ->whereIn('id', $eintraege->where('aktion', 'karte_geloescht')->pluck('karte_id')->filter()->all())
            ->pluck('deleted_at', 'id');

        return [
            'eintraege' => $eintraege->map(fn (ErgebnisVerlauf $e) => $this->darstellen($e, $geloescht->all()))->values()->all(),
            'mehr' => $mehr,
        ];
    }

    /** @param  array<int, mixed>  $wiederherstellbar  karte_id => deleted_at */
    public function darstellen(ErgebnisVerlauf $e, array $wiederherstellbar = []): array
    {
        $d = $e->details ?? [];
        $karteGeloeschtAm = $e->aktion === 'karte_geloescht' && $e->karte_id ? ($wiederherstellbar[$e->karte_id] ?? null) : null;

        return [
            'id' => $e->id,
            'aktion' => $e->aktion,
            'karte_id' => $e->karte_id,
            'person' => $e->user?->name ?? ($e->user_id ? 'Ehemalige Person' : 'System'),
            'zeit' => $e->created_at?->toIso8601String(),
            'icon' => $this->icon($e->aktion),
            'text' => $this->text($e->aktion, $d),
            'zeilen' => $this->zeilen($e->aktion, $d),
            'wiederherstellbar' => $karteGeloeschtAm !== null,
            'endgueltig_am' => $karteGeloeschtAm
                ? Carbon::parse($karteGeloeschtAm)->addDays(ErgebnisKarte::AUFBEWAHRUNG_TAGE)->toDateString()
                : null,
        ];
    }

    private function text(string $aktion, array $d): string
    {
        $k = '„'.($d['karte'] ?? 'Karte').'“';

        return match ($aktion) {
            'board_angelegt' => 'hat das Board angelegt',
            'board_dupliziert' => 'hat das Board als Kopie von „'.($d['quelle'] ?? 'einem Board').'“ angelegt',
            'board_geaendert' => 'hat '.$this->aufzaehlung(array_map(fn ($f) => self::FELDNAMEN[$f] ?? $f, array_keys($d['aenderungen'] ?? []))).' geändert',
            'felder_geaendert' => 'hat die Felder geändert',
            'zugriffe_geaendert' => 'hat die Zugriffe geändert',
            'board_archiviert' => 'hat das Board archiviert',
            'board_wiederhergestellt' => 'hat das Board aus dem Archiv geholt',
            'karte_angelegt' => 'hat die Karte '.$k.' in „'.($d['feld'] ?? '?').'“ angelegt',
            'karte_geaendert' => 'hat die Karte '.$k.' geändert',
            'karte_verschoben' => ! empty($d['automatisch'])
                ? 'Aufgabe geändert – die Karte '.$k.' wanderte von „'.($d['von'] ?? '?').'“ nach „'.($d['nach'] ?? '?').'“'
                : 'hat die Karte '.$k.' von „'.($d['von'] ?? '?').'“ nach „'.($d['nach'] ?? '?').'“ verschoben',
            'zustaendige_geaendert' => 'hat die Zuständigen der Karte '.$k.' geändert',
            'karte_geloescht' => 'hat die Karte '.$k.' aus „'.($d['feld'] ?? '?').'“ gelöscht',
            'karte_wiederhergestellt' => 'hat die Karte '.$k.' in „'.($d['feld'] ?? '?').'“ wiederhergestellt',
            'anhang_hinzugefuegt' => 'hat „'.($d['datei'] ?? 'eine Datei').'“ an die Karte '.$k.' angehängt',
            'anhang_entfernt' => 'hat „'.($d['datei'] ?? 'eine Datei').'“ von der Karte '.$k.' entfernt',
            'aufgabe_angelegt' => 'hat aus der Karte '.$k.' eine Aufgabe gemacht',
            'aufgabe_erledigt' => 'hat die Aufgabe zur Karte '.$k.' erledigt',
            default => $aktion,
        };
    }

    /**
     * Detailzeilen unter dem Eintrag (alt → neu, hinzugefügt/entfernt).
     *
     * @return array<int, string>
     */
    private function zeilen(string $aktion, array $d): array
    {
        $zeilen = [];
        foreach ($d['aenderungen'] ?? [] as $feld => [$alt, $neu]) {
            $name = self::FELDNAMEN[$feld] ?? ($feld === 'titel' ? 'Titel' : $feld);
            $zeilen[] = $name.': '.$this->wert($alt).' → '.$this->wert($neu);
        }
        foreach ($d['hinzugefuegt'] ?? [] as $x) {
            $zeilen[] = '+ '.$x;
        }
        foreach ($d['entfernt'] ?? [] as $x) {
            $zeilen[] = '− '.$x;
        }
        foreach ($d['umbenannt'] ?? [] as [$alt, $neu]) {
            $zeilen[] = '„'.$alt.'“ → „'.$neu.'“';
        }
        foreach ($d['angepasst'] ?? [] as $x) {
            $zeilen[] = $x;
        }
        if (! empty($d['ersatzfeld'])) {
            $zeilen[] = 'Das ursprüngliche Feld gibt es nicht mehr.';
        }

        return $zeilen;
    }

    private function wert(mixed $wert): string
    {
        if ($wert === null || $wert === '') {
            return '(leer)';
        }
        if (is_bool($wert)) {
            return $wert ? 'ja' : 'nein';
        }
        if (is_string($wert) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            return Carbon::parse($wert)->format('d.m.Y');
        }
        $text = is_scalar($wert) ? (string) $wert : json_encode($wert, JSON_UNESCAPED_UNICODE);

        return '„'.mb_strimwidth($text, 0, 120, '…').'“';
    }

    private function aufzaehlung(array $teile): string
    {
        $teile = array_values(array_unique($teile));
        if (count($teile) <= 1) {
            return $teile[0] ?? 'das Board';
        }
        $letztes = array_pop($teile);

        return implode(', ', $teile).' und '.$letztes;
    }

    private function icon(string $aktion): string
    {
        return match ($aktion) {
            'board_angelegt', 'board_dupliziert' => 'fas fa-plus-circle',
            'board_geaendert' => 'fas fa-heading',
            'felder_geaendert' => 'fas fa-th-list',
            'zugriffe_geaendert' => 'fas fa-user-shield',
            'board_archiviert' => 'fas fa-archive',
            'board_wiederhergestellt', 'karte_wiederhergestellt' => 'fas fa-undo',
            'karte_angelegt' => 'far fa-sticky-note',
            'karte_geaendert' => 'fas fa-pen',
            'karte_verschoben' => 'fas fa-arrows-alt',
            'zustaendige_geaendert' => 'fas fa-users',
            'karte_geloescht' => 'far fa-trash-alt',
            'anhang_hinzugefuegt', 'anhang_entfernt' => 'fas fa-paperclip',
            'aufgabe_angelegt', 'aufgabe_erledigt' => 'fas fa-tasks',
            default => 'fas fa-history',
        };
    }

    // ------------------------------------------------------------------
    // Diffs
    // ------------------------------------------------------------------

    /**
     * Unterschiede zweier flacher Wertelisten: [feld => [alt, neu]].
     */
    public function unterschiede(array $alt, array $neu): array
    {
        $aenderungen = [];
        foreach ($neu as $feld => $wert) {
            $vorher = $alt[$feld] ?? null;
            if ($this->normal($vorher) !== $this->normal($wert)) {
                $aenderungen[$feld] = [$vorher, $wert];
            }
        }

        return $aenderungen;
    }

    private function normal(mixed $wert): mixed
    {
        if ($wert === '') {
            return null;
        }
        if ($wert instanceof \DateTimeInterface) {
            return $wert->format('Y-m-d');
        }

        return is_array($wert) ? json_encode($wert) : $wert;
    }

    /**
     * Felder-Diff: hinzugefügt, entfernt, umbenannt, sonst angepasst (Farbe, Hinweis …).
     */
    public function felderDiff(array $alt, array $neu): array
    {
        $alt = collect($alt)->keyBy('schluessel');
        $neu = collect($neu)->keyBy('schluessel');
        $titel = function (array $b, $liste) {
            $eltern = ($b['eltern'] ?? null) ? ($liste[$b['eltern']]['titel'] ?? null) : null;

            return $eltern ? $eltern.' › '.$b['titel'] : $b['titel'];
        };

        $diff = ['hinzugefuegt' => [], 'entfernt' => [], 'umbenannt' => [], 'angepasst' => []];
        foreach ($neu as $s => $b) {
            if (! $alt->has($s)) {
                $diff['hinzugefuegt'][] = $titel($b, $neu);

                continue;
            }
            $a = $alt[$s];
            if (($a['titel'] ?? '') !== ($b['titel'] ?? '')) {
                $diff['umbenannt'][] = [$a['titel'], $b['titel']];
            }
            $teile = [];
            foreach (['farbe' => 'Farbe', 'hinweis' => 'Hinweis', 'badge' => 'Badge', 'raster' => 'Kartenspalten', 'status' => 'Status'] as $f => $name) {
                if (($a[$f] ?? null) != ($b[$f] ?? null)) {
                    $teile[] = $name;
                }
            }
            if ($teile) {
                $diff['angepasst'][] = $titel($b, $neu).': '.implode(', ', $teile);
            }
        }
        foreach ($alt as $s => $b) {
            if (! $neu->has($s)) {
                $diff['entfernt'][] = $titel($b, $alt);
            }
        }
        if (empty(array_filter($diff)) && $alt->keys()->all() !== $neu->keys()->all()) {
            $diff['angepasst'][] = 'Reihenfolge geändert';
        }

        return array_filter($diff);
    }
}
