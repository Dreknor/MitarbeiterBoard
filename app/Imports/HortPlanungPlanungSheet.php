<?php

namespace App\Imports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Parst das „Planung"-Sheet der Excel-Vorlage.
 *
 * Erwartete Struktur (analog Konzept §2.1):
 *   Zeile 1  – Monatslabels (merged, 2 Spalten pro Monat: SP1 / SP2)
 *   Zeile 2  – SP1 / SP2 Sub-Header
 *   Zeile 3  – Kinderanzahl
 *   Zeile 4  – Vollzeitstunden
 *   Zeile 5+ – Zusatzstunden-Typen (variabel)
 *   Zeile 8+ – Personendaten (Spalte A = Name, dann SP1/SP2 pro Monat)
 */
class HortPlanungPlanungSheet implements ToCollection
{
    // ── Ergebnis-Arrays (nach dem Import befüllbar) ───────────────

    /** @var array<string>  erkannte Monatslabels ('Y-m-01') */
    private array $monate = [];

    /** @var array<int>  Spaltenindizes (0-basiert) der SP1-Spalten */
    private array $sp1Cols = [];

    /** @var array<int>  Spaltenindizes (0-basiert) der SP2-Spalten */
    private array $sp2Cols = [];

    /** @var array<array{name:string, sp1:array, sp2:array}> */
    private array $personen = [];

    /** @var array<string, array<string, float>>  'kinderanzahl', 'vollzeitstunden', Zusatztyp-Kürzel → [Y-m → Wert] */
    private array $parameter = [];

    /** @var array<array{bezeichnung:string, werte:array<string,float>}> Zusatzstunden-Zeilen */
    private array $zusatzzeilen = [];

    // ── ToCollection ────────────────────────────────────────────────

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $rowarr = $rows->toArray();

        // ── Schritt 1: Monat-Spalten aus Zeile 1 erkennen ────────────
        $headerRow = array_values($rowarr[0] ?? []);
        $this->erkenneMonatSpalten($headerRow);

        if (empty($this->monate)) {
            return;
        }

        // ── Schritt 2: Parameter aus Zeilen 2–7 lesen ────────────────
        $paramLabels = ['kinderanzahl', 'vollzeitstunden'];
        $zusatzZeilenRaw = [];

        for ($i = 1; $i <= 7; $i++) {
            $row = array_values($rowarr[$i] ?? []);
            if (empty($row) || ($row[0] === null && count(array_filter($row)) === 0)) {
                continue;
            }

            $label = strtolower(trim((string) ($row[0] ?? '')));

            if (str_contains($label, 'kind')) {
                $this->leseParameterZeile('kinderanzahl', $row);
            } elseif (str_contains($label, 'vollzeit') || str_contains($label, 'stunden')) {
                $this->leseParameterZeile('vollzeitstunden', $row);
            } elseif (!in_array($label, ['', 'sp1', 'sp2', 'monat']) && !empty($label)) {
                // Mögliche Zusatzstunden-Zeile
                $zusatzZeilenRaw[] = ['bezeichnung' => ucfirst($label), 'row' => $row];
            }
        }

        foreach ($zusatzZeilenRaw as $z) {
            $werte = [];
            foreach ($this->monate as $idx => $monat) {
                $werte[$monat] = (float) ($z['row'][$this->sp1Cols[$idx]] ?? 0);
            }
            $this->zusatzzeilen[] = ['bezeichnung' => $z['bezeichnung'], 'werte' => $werte];
        }

        // ── Schritt 3: Personenzeilen ab Zeile 8 lesen ───────────────
        for ($i = 7; $i < count($rowarr); $i++) {
            $row = array_values($rowarr[$i] ?? []);
            $name = trim((string) ($row[0] ?? ''));

            if (empty($name) || is_numeric($name)) {
                continue;
            }

            // Berechnungszeilen überspringen
            $lowerName = strtolower($name);
            if (
                str_contains($lowerName, 'summe') ||
                str_contains($lowerName, 'vzä') ||
                str_contains($lowerName, 'budget') ||
                str_contains($lowerName, 'gesetz') ||
                str_contains($lowerName, 'differenz')
            ) {
                continue;
            }

            $sp1Werte = [];
            $sp2Werte = [];

            foreach ($this->monate as $idx => $monat) {
                $sp1Raw = $row[$this->sp1Cols[$idx]] ?? null;
                $sp2Raw = $row[$this->sp2Cols[$idx]] ?? null;

                // #REF!-Fehler und fehlerhafte Werte als 0 behandeln (§13, Entscheidung 4)
                $sp1Werte[$monat] = is_numeric($sp1Raw) ? (float) $sp1Raw : 0;
                $sp2Werte[$monat] = is_numeric($sp2Raw) ? (float) $sp2Raw : 0;
            }

            $this->personen[] = [
                'name'     => $name,
                'sp1'      => $sp1Werte,
                'sp2'      => $sp2Werte,
            ];
        }
    }

    // ── Private Hilfsmethoden ────────────────────────────────────────

    /**
     * Erkennt die Monatsspalten anhand von Datumsangaben in der Header-Zeile.
     * Jeder Monat belegt 2 Spalten: SP1 (gerade) + SP2 (ungerade).
     */
    private function erkenneMonatSpalten(array $headerRow): void
    {
        foreach ($headerRow as $colIdx => $wert) {
            if ($wert === null || $wert === '') {
                continue;
            }

            $datum = $this->parseMonatWert($wert);
            if ($datum === null) {
                continue;
            }

            $this->monate[]  = $datum->format('Y-m-01');
            $this->sp1Cols[] = $colIdx;
            $this->sp2Cols[] = $colIdx + 1;
        }
    }

    /**
     * Parst einen Zellwert als Monat (Carbon).
     * Unterstützt: Carbon-Objekte, Excel-Seriennummern (float/int), Strings.
     */
    private function parseMonatWert(mixed $wert): ?Carbon
    {
        if ($wert instanceof Carbon) {
            return $wert->startOfMonth();
        }

        // Excel-Seriennummer (float, z.B. 45292.0 = 2024-01-01)
        if (is_float($wert) || (is_int($wert) && $wert > 1000)) {
            try {
                return Carbon::createFromTimestamp(($wert - 25569) * 86400)->startOfMonth();
            } catch (\Throwable) {
                return null;
            }
        }

        // String-Format: "Jan 2024", "Januar 2024", "01.2024", "2024-01"
        if (is_string($wert)) {
            $clean = trim($wert);
            if (strlen($clean) < 4) {
                return null;
            }
            try {
                return Carbon::parse($clean)->startOfMonth();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Liest Parameterwerte (kinderanzahl, vollzeitstunden) für alle Monate aus einer Zeile.
     */
    private function leseParameterZeile(string $schluessel, array $row): void
    {
        foreach ($this->monate as $idx => $monat) {
            $wert = $row[$this->sp1Cols[$idx]] ?? null;
            $this->parameter[$schluessel][$monat] = is_numeric($wert) ? (float) $wert : null;
        }
    }

    // ── Getter ───────────────────────────────────────────────────────

    public function getMonate(): array
    {
        return $this->monate;
    }

    public function getPersonen(): array
    {
        return $this->personen;
    }

    public function getPersonNamen(): array
    {
        return array_column($this->personen, 'name');
    }

    public function getParameter(): array
    {
        return $this->parameter;
    }

    public function getZusatzzeilen(): array
    {
        return $this->zusatzzeilen;
    }
}

