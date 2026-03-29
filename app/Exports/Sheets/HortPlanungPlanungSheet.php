<?php

namespace App\Exports\Sheets;

use App\Models\personal\HortFaktorWert;
use App\Models\personal\HortPlanung;
use App\Services\HortPlanungService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * „Planung"-Sheet: Horizontale Matrix analog zur Excel-Vorlage.
 * Zwei Spalten pro Monat (SP1 / SP2), Berechnungszeilen unten.
 */
class HortPlanungPlanungSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected HortPlanung $planung;
    protected Collection  $monate;
    protected array       $berechnungen;
    protected Collection  $personen;
    protected array       $rowTypes = [];   // Tracking für Styling

    // Farbpalette (Hex ohne #)
    protected const FARBE_HEADER     = '2563EB'; // Blau
    protected const FARBE_PARAMETER  = 'DBEAFE'; // Hellblau
    protected const FARBE_PERSON     = 'F9FAFB'; // Sehr helles Grau
    protected const FARBE_SUMME      = 'FED7AA'; // Orange
    protected const FARBE_FAKTOR     = 'D1FAE5'; // Grün
    protected const FARBE_ERGEBNIS   = 'FEF9C3'; // Gelb
    protected const FARBE_POSITIV    = 'D1FAE5'; // Grün
    protected const FARBE_NEGATIV    = 'FEE2E2'; // Rot

    public function __construct(HortPlanung $planung, HortPlanungService $service)
    {
        $this->planung = $planung;
        $this->monate  = $planung->monate;

        // Berechnungen vorberechnen
        $this->berechnungen = $this->monate->mapWithKeys(fn($m) => [
            $m->monat->format('Y-m') => $service->berechneMonat($m),
        ])->toArray();

        // Eindeutige Personen alphabetisch
        $this->personen = $planung->monate
            ->flatMap(fn($m) => $m->personen)
            ->unique('user_id')
            ->sortBy(fn($p) => $p->user?->name ?? 'zzz')
            ->values();
    }

    // ── Hilfsfunktionen ─────────────────────────────────────────────

    /**
     * Gibt den Excel-Spaltenbuchstaben für einen 1-basierten Index zurück.
     * 1=A, 26=Z, 27=AA, 28=AB, ...
     */
    protected static function colLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $mod    = ($col - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $col    = (int) (($col - $mod - 1) / 26);
        }
        return $letter;
    }

    /** Spalten-Index (1-basiert) für SP1 eines Monats (0-basierter Monat-Index) */
    protected function sp1Col(int $monatIdx): int
    {
        return 2 + $monatIdx * 2;   // Col 2 = B für Monat 0
    }

    /** Spalten-Index (1-basiert) für SP2 eines Monats */
    protected function sp2Col(int $monatIdx): int
    {
        return 3 + $monatIdx * 2;
    }

    // ── FromArray ───────────────────────────────────────────────────

    public function array(): array
    {
        $rows    = [];
        $monatListe = $this->monate->values();
        $anzahl  = $monatListe->count();

        // ── Zeile 1: Titel ────────────────────────────────────────────
        $titleRow = array_fill(0, 1 + $anzahl * 2, '');
        $titleRow[0] = 'Hortstunden-Planung: ' . $this->planung->name;
        $rows[]       = $titleRow;
        $this->rowTypes[] = 'titel';

        // ── Zeile 2: Monat-Header ─────────────────────────────────────
        $headerRow = array_fill(0, 1 + $anzahl * 2, '');
        $headerRow[0] = 'Monat';
        foreach ($monatListe as $idx => $monat) {
            $headerRow[1 + $idx * 2] = $monat->monat->translatedFormat('M Y');
            $headerRow[2 + $idx * 2] = '';
        }
        $rows[]           = $headerRow;
        $this->rowTypes[] = 'monat_header';

        // ── Zeile 3: SP1/SP2 Sub-Header ──────────────────────────────
        $subRow = array_fill(0, 1 + $anzahl * 2, '');
        $subRow[0] = '';
        foreach ($monatListe as $idx => $monat) {
            $subRow[1 + $idx * 2] = 'SP1';
            $subRow[2 + $idx * 2] = 'SP2';
        }
        $rows[]           = $subRow;
        $this->rowTypes[] = 'sp_header';

        // ── Zeile 4: Kinderanzahl ─────────────────────────────────────
        $kindRow    = array_fill(0, 1 + $anzahl * 2, '');
        $kindRow[0] = 'Kinderanzahl';
        foreach ($monatListe as $idx => $monat) {
            $kindRow[1 + $idx * 2] = $monat->kinderanzahl;
            $kindRow[2 + $idx * 2] = '';
        }
        $rows[]           = $kindRow;
        $this->rowTypes[] = 'parameter';

        // ── Zeile 5: Vollzeitstunden ──────────────────────────────────
        $vzRow    = array_fill(0, 1 + $anzahl * 2, '');
        $vzRow[0] = 'Vollzeitstunden';
        foreach ($monatListe as $idx => $monat) {
            $vzRow[1 + $idx * 2] = $monat->vollzeitstunden;
            $vzRow[2 + $idx * 2] = '';
        }
        $rows[]           = $vzRow;
        $this->rowTypes[] = 'parameter';

        // ── Zeilen 6+: Zusatzstunden-Typen ───────────────────────────
        $zusatzTypen = $this->planung->zusatzstundenTypen->where('aktiv', true);
        foreach ($zusatzTypen as $typ) {
            $zusatzRow    = array_fill(0, 1 + $anzahl * 2, '');
            $zusatzRow[0] = $typ->bezeichnung;
            foreach ($monatListe as $idx => $monat) {
                $mk   = $monat->monat->format('Y-m');
                $b    = $this->berechnungen[$mk] ?? [];
                $summe = 0;
                foreach ($b['zusatzstunden'] ?? [] as $z) {
                    if (($z['kuerzel'] ?? '') === $typ->kuerzel) {
                        $summe = $z['stunden'];
                        break;
                    }
                }
                $zusatzRow[1 + $idx * 2] = $this->zahl($summe);
                $zusatzRow[2 + $idx * 2] = '';
            }
            $rows[]           = $zusatzRow;
            $this->rowTypes[] = 'parameter';
        }

        // ── Leerzeile ────────────────────────────────────────────────
        $rows[]           = array_fill(0, 1 + $anzahl * 2, '');
        $this->rowTypes[] = 'leer';

        // ── Personenzeilen ────────────────────────────────────────────
        foreach ($this->personen as $person) {
            $personRow    = array_fill(0, 1 + $anzahl * 2, '');
            $personRow[0] = $person->user?->name ?? '–';
            foreach ($monatListe as $idx => $monat) {
                $eintrag = $monat->personen->firstWhere('user_id', $person->user_id);
                $personRow[1 + $idx * 2] = $this->zahl($eintrag?->stunden_gesamt);
                $personRow[2 + $idx * 2] = $this->zahl($eintrag?->stunden_stadt);
            }
            $rows[]           = $personRow;
            $this->rowTypes[] = 'person';
        }

        // ── Summenzeile SP1 ───────────────────────────────────────────
        $sumSp1Row    = array_fill(0, 1 + $anzahl * 2, '');
        $sumSp1Row[0] = 'Σ Stunden SP1';
        foreach ($monatListe as $idx => $monat) {
            $mk = $monat->monat->format('Y-m');
            $sumSp1Row[1 + $idx * 2] = $this->zahl($this->berechnungen[$mk]['summe_sp1'] ?? 0);
            $sumSp1Row[2 + $idx * 2] = '';
        }
        $rows[]           = $sumSp1Row;
        $this->rowTypes[] = 'summe';

        // ── Summenzeile SP2 ───────────────────────────────────────────
        $sumSp2Row    = array_fill(0, 1 + $anzahl * 2, '');
        $sumSp2Row[0] = 'Σ Stunden SP2';
        foreach ($monatListe as $idx => $monat) {
            $mk = $monat->monat->format('Y-m');
            $sumSp2Row[1 + $idx * 2] = '';
            $sumSp2Row[2 + $idx * 2] = $this->zahl($this->berechnungen[$mk]['summe_sp2'] ?? 0);
        }
        $rows[]           = $sumSp2Row;
        $this->rowTypes[] = 'summe';

        // ── VZÄ SP1 ───────────────────────────────────────────────────
        $vzaSp1Row    = array_fill(0, 1 + $anzahl * 2, '');
        $vzaSp1Row[0] = 'VZÄ SP1';
        foreach ($monatListe as $idx => $monat) {
            $mk = $monat->monat->format('Y-m');
            $vzaSp1Row[1 + $idx * 2] = $this->zahl($this->berechnungen[$mk]['summe_vz_sp1'] ?? 0, 4);
            $vzaSp1Row[2 + $idx * 2] = '';
        }
        $rows[]           = $vzaSp1Row;
        $this->rowTypes[] = 'summe';

        // ── VZÄ SP2 ───────────────────────────────────────────────────
        $vzaSp2Row    = array_fill(0, 1 + $anzahl * 2, '');
        $vzaSp2Row[0] = 'VZÄ SP2';
        foreach ($monatListe as $idx => $monat) {
            $mk = $monat->monat->format('Y-m');
            $vzaSp2Row[1 + $idx * 2] = '';
            $vzaSp2Row[2 + $idx * 2] = $this->zahl($this->berechnungen[$mk]['summe_vz_sp2'] ?? 0, 4);
        }
        $rows[]           = $vzaSp2Row;
        $this->rowTypes[] = 'summe';

        // ── Dynamische Faktor-Zeilen ──────────────────────────────────
        $aktiveFaktoren = $this->planung->faktoren->where('aktiv', true)->sortBy('position');
        foreach ($aktiveFaktoren as $faktor) {
            $fRow    = array_fill(0, 1 + $anzahl * 2, '');
            $fRow[0] = $faktor->bezeichnung;
            foreach ($monatListe as $idx => $monat) {
                $mk  = $monat->monat->format('Y-m');
                $vz  = $this->berechnungen[$mk]['faktoren'][$faktor->kuerzel]['vz'] ?? 0;
                $fRow[1 + $idx * 2] = $this->zahl($vz, 4);
                $fRow[2 + $idx * 2] = '';
            }
            $rows[]           = $fRow;
            $this->rowTypes[] = 'faktor';
        }

        // ── Summe gesetz. VZÄ ─────────────────────────────────────────
        $sumGesRow    = array_fill(0, 1 + $anzahl * 2, '');
        $sumGesRow[0] = 'Σ gesetzl. VZÄ';
        foreach ($monatListe as $idx => $monat) {
            $mk = $monat->monat->format('Y-m');
            $sumGesRow[1 + $idx * 2] = $this->zahl($this->berechnungen[$mk]['summe_gesetz_vz'] ?? 0, 4);
            $sumGesRow[2 + $idx * 2] = '';
        }
        $rows[]           = $sumGesRow;
        $this->rowTypes[] = 'ergebnis';

        // ── Stunden gesetzl. Minimum ──────────────────────────────────
        $stundenGesRow    = array_fill(0, 1 + $anzahl * 2, '');
        $stundenGesRow[0] = 'Stunden gesetzl. Minimum';
        foreach ($monatListe as $idx => $monat) {
            $mk = $monat->monat->format('Y-m');
            $stundenGesRow[1 + $idx * 2] = $this->zahl($this->berechnungen[$mk]['summe_stunden_gesetzl'] ?? 0);
            $stundenGesRow[2 + $idx * 2] = '';
        }
        $rows[]           = $stundenGesRow;
        $this->rowTypes[] = 'ergebnis';

        // ── Budget-Rest SP1 ───────────────────────────────────────────
        $budgetRow    = array_fill(0, 1 + $anzahl * 2, '');
        $budgetRow[0] = 'Budget-Rest (SP1)';
        foreach ($monatListe as $idx => $monat) {
            $mk   = $monat->monat->format('Y-m');
            $rest = $this->berechnungen[$mk]['budget_rest_sp1'] ?? 0;
            $budgetRow[1 + $idx * 2] = $this->zahl($rest);
            $budgetRow[2 + $idx * 2] = '';
        }
        $rows[]           = $budgetRow;
        $this->rowTypes[] = 'budget_rest';

        // ── Differenz VZÄ SP2 ────────────────────────────────────────
        $diffVzRow    = array_fill(0, 1 + $anzahl * 2, '');
        $diffVzRow[0] = 'Differenz VZÄ Stadt';
        foreach ($monatListe as $idx => $monat) {
            $mk = $monat->monat->format('Y-m');
            $diffVzRow[1 + $idx * 2] = '';
            $diffVzRow[2 + $idx * 2] = $this->zahl($this->berechnungen[$mk]['differenz_vz_sp2'] ?? 0, 4);
        }
        $rows[]           = $diffVzRow;
        $this->rowTypes[] = 'ergebnis';

        // ── Exporthinweis ─────────────────────────────────────────────
        $hinweisRow    = array_fill(0, 1 + $anzahl * 2, '');
        $hinweisRow[0] = 'Exportiert am ' . now()->format('d.m.Y H:i') . ' Uhr';
        $rows[]         = $hinweisRow;
        $this->rowTypes[] = 'hinweis';

        return $rows;
    }

    // ── WithStyles ───────────────────────────────────────────────────

    public function styles(Worksheet $sheet): array
    {
        $monatAnzahl = $this->monate->count();
        $lastCol     = self::colLetter(1 + $monatAnzahl * 2);
        $totalRows   = count($this->rowTypes);

        // Spalte A fixieren (Sticky)
        $sheet->freezePane('B1');

        // ── Spaltenbreiten ─────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(26);
        for ($i = 0; $i < $monatAnzahl * 2; $i++) {
            $sheet->getColumnDimension(self::colLetter(2 + $i))->setWidth(8);
        }

        $styles = [];

        // ── Titelzeile ────────────────────────────────────────────────
        $styles["A1:{$lastCol}1"] = [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::FARBE_HEADER]],
        ];

        // ── Zeilenspezifisches Styling ────────────────────────────────
        foreach ($this->rowTypes as $rowIdx => $type) {
            $excelRow = $rowIdx + 1;
            $range    = "A{$excelRow}:{$lastCol}{$excelRow}";

            $farbe = match ($type) {
                'monat_header', 'sp_header' => ['rgb' => self::FARBE_HEADER],
                'parameter'                  => ['rgb' => self::FARBE_PARAMETER],
                'person'                     => ['rgb' => 'FFFFFF'],
                'summe'                      => ['rgb' => self::FARBE_SUMME],
                'faktor'                     => ['rgb' => self::FARBE_FAKTOR],
                'ergebnis', 'budget_rest'    => ['rgb' => self::FARBE_ERGEBNIS],
                default                      => null,
            };

            if ($farbe) {
                $styles[$range] = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => $farbe],
                ];
            }

            if (in_array($type, ['monat_header', 'sp_header', 'summe', 'ergebnis', 'budget_rest'])) {
                $styles[$range]['font'] = ['bold' => true];
            }
        }

        // ── Rahmen für Datenbereich ───────────────────────────────────
        $styles["A1:{$lastCol}{$totalRows}"]['borders'] = [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'D1D5DB'],
            ],
        ];

        // ── Alignment ─────────────────────────────────────────────────
        $styles["B1:{$lastCol}{$totalRows}"]['alignment'] = [
            'horizontal' => Alignment::HORIZONTAL_RIGHT,
        ];

        return $styles;
    }

    // ── WithColumnWidths ────────────────────────────────────────────

    public function columnWidths(): array
    {
        $widths = ['A' => 26];
        for ($i = 0; $i < $this->monate->count() * 2; $i++) {
            $widths[self::colLetter(2 + $i)] = 8;
        }
        return $widths;
    }

    // ── WithTitle ────────────────────────────────────────────────────

    public function title(): string
    {
        return 'Planung';
    }

    // ── Hilfsmethode Zahlenformat ────────────────────────────────────

    protected function zahl(?float $wert, int $stellen = 2): string
    {
        if ($wert === null) {
            return '';
        }
        return number_format($wert, $stellen, ',', '.');
    }
}

