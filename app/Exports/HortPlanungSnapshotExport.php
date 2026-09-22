<?php

namespace App\Exports;

use App\Models\personal\HortPlanungSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel-Export eines Hortstunden-Planungs-Snapshots.
 * Liest alle Daten direkt aus dem gespeicherten JSON – keine DB-Queries für Planungsdaten.
 */
class HortPlanungSnapshotExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected HortPlanungSnapshot $snapshot;
    protected array $daten;       // dekodiertes JSON
    protected array $userNames;   // [user_id => name]
    protected array $monate;      // sortierte Monat-Strings (Y-m-d)
    protected array $rowTypes = [];

    // Farben
    protected const C_HEADER    = '2563EB';
    protected const C_PARAM     = 'DBEAFE';
    protected const C_PERSON    = 'F9FAFB';
    protected const C_SUMME     = 'FED7AA';
    protected const C_ERGEBNIS  = 'FEF9C3';
    protected const C_POSITIV   = 'D1FAE5';
    protected const C_NEGATIV   = 'FEE2E2';
    protected const C_SNAPSHOT  = 'EDE9FE'; // Lila – Snapshot-Info

    public function __construct(HortPlanungSnapshot $snapshot)
    {
        $this->snapshot = $snapshot;
        $this->snapshot->loadMissing(['planung', 'ersteller']);

        $this->daten  = $snapshot->daten ?? [];
        $this->monate = collect($this->daten)->pluck('monat')->sort()->values()->toArray();

        // User-Namen einmal aus DB laden
        $userIds = collect($this->daten)
            ->flatMap(fn($m) => collect($m['personen'])->pluck('user_id'))
            ->unique()->values()->toArray();

        $this->userNames = User::whereIn('id', $userIds)
            ->pluck('name', 'id')
            ->toArray();
    }

    public function title(): string
    {
        return 'Snapshot';
    }

    // ── Hilfsfunktionen ─────────────────────────────────────────────

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

    protected function sp1Col(int $idx): int { return 2 + $idx * 2; }
    protected function sp2Col(int $idx): int { return 3 + $idx * 2; }

    // ── Daten ────────────────────────────────────────────────────────

    public function array(): array
    {
        $rows   = [];
        $anzahl = count($this->monate);

        // Index: monat (Y-m-d) → Eintrag
        $byMonat = collect($this->daten)->keyBy('monat');

        // Eindeutige Personen (user_id) über alle Monate, nach Name sortiert
        $alleUserIds = collect($this->daten)
            ->flatMap(fn($m) => collect($m['personen'])->pluck('user_id'))
            ->unique()
            ->sortBy(fn($uid) => $this->userNames[$uid] ?? 'zzz')
            ->values()
            ->toArray();

        // ── Info-Zeile 1: Snapshot-Metadaten ─────────────────────────
        $infoRow = array_fill(0, 1 + $anzahl * 2, '');
        $infoRow[0] = 'Snapshot: ' . $this->snapshot->name
            . ' | Planung: ' . ($this->snapshot->planung?->name ?? '–')
            . ' | Erstellt: ' . $this->snapshot->created_at?->format('d.m.Y H:i')
            . ' | Ersteller: ' . ($this->snapshot->ersteller?->name ?? '–');
        $rows[]           = $infoRow;
        $this->rowTypes[] = 'info';

        // ── Zeile 2: Monat-Header ─────────────────────────────────────
        $headerRow    = array_fill(0, 1 + $anzahl * 2, '');
        $headerRow[0] = 'Monat';
        foreach ($this->monate as $idx => $monat) {
            $headerRow[1 + $idx * 2] = Carbon::parse($monat)->translatedFormat('M Y');
            $headerRow[2 + $idx * 2] = '';
        }
        $rows[]           = $headerRow;
        $this->rowTypes[] = 'monat_header';

        // ── Zeile 3: SP1/SP2 Sub-Header ──────────────────────────────
        $subRow    = array_fill(0, 1 + $anzahl * 2, '');
        $subRow[0] = '';
        foreach ($this->monate as $idx => $monat) {
            $subRow[1 + $idx * 2] = 'SP1 (Verein)';
            $subRow[2 + $idx * 2] = 'SP2 (Stadt)';
        }
        $rows[]           = $subRow;
        $this->rowTypes[] = 'sp_header';

        // ── Parameter ────────────────────────────────────────────────
        $kindRow    = array_fill(0, 1 + $anzahl * 2, '');
        $kindRow[0] = 'Kinderanzahl';
        $vzRow      = array_fill(0, 1 + $anzahl * 2, '');
        $vzRow[0]   = 'Vollzeitstunden';
        foreach ($this->monate as $idx => $monat) {
            $param              = $byMonat[$monat]['parameter'] ?? [];
            $kindRow[1 + $idx * 2] = $param['kinderanzahl']    ?? '';
            $kindRow[2 + $idx * 2] = '';
            $vzRow[1 + $idx * 2]   = $param['vollzeitstunden'] ?? '';
            $vzRow[2 + $idx * 2]   = '';
        }
        $rows[]           = $kindRow;
        $this->rowTypes[] = 'parameter';
        $rows[]           = $vzRow;
        $this->rowTypes[] = 'parameter';

        // ── Personen ─────────────────────────────────────────────────
        foreach ($alleUserIds as $uid) {
            $personRow    = array_fill(0, 1 + $anzahl * 2, '');
            $personRow[0] = $this->userNames[$uid] ?? 'User #' . $uid;
            foreach ($this->monate as $idx => $monat) {
                $personen = collect($byMonat[$monat]['personen'] ?? []);
                $p        = $personen->firstWhere('user_id', $uid);
                $personRow[1 + $idx * 2] = $p['stunden_gesamt'] ?? '';
                $personRow[2 + $idx * 2] = $p['stunden_stadt']  ?? '';
            }
            $rows[]           = $personRow;
            $this->rowTypes[] = 'person';
        }

        // ── Summenwerte ───────────────────────────────────────────────
        $sp1Row = array_fill(0, 1 + $anzahl * 2, '');  $sp1Row[0] = 'Σ SP1 (Vereinsstunden)';
        $sp2Row = array_fill(0, 1 + $anzahl * 2, '');  $sp2Row[0] = 'Σ SP2 (Stadtstunden)';
        foreach ($this->monate as $idx => $monat) {
            $b = $byMonat[$monat]['berechnungen'] ?? [];
            $sp1Row[1 + $idx * 2] = $b['summe_sp1'] ?? '';
            $sp1Row[2 + $idx * 2] = '';
            $sp2Row[1 + $idx * 2] = '';
            $sp2Row[2 + $idx * 2] = $b['summe_sp2'] ?? '';
        }
        $rows[]           = $sp1Row;
        $this->rowTypes[] = 'summe';
        $rows[]           = $sp2Row;
        $this->rowTypes[] = 'summe';

        // ── Berechnungswerte ──────────────────────────────────────────
        $fields = [
            'summe_stunden_gesetzl' => ['Gesetzlicher Bedarf (Std.)',  'ergebnis'],
            'budget_gesamt'         => ['Budget gesamt (Std.)',        'ergebnis'],
            'budget_rest_sp1'       => ['Budget-Rest SP1',            'delta'],
            'differenz_stadt'       => ['Differenz SP2 – Gesetzl.',   'delta'],
            'betreuungsschluessel'  => ['Betreuungsschlüssel (VZÄ)',  'ergebnis'],
            'summe_gesetz_vz'       => ['Gesetzlicher Bedarf (VZÄ)',  'ergebnis'],
            'summe_vz_sp1'          => ['VZÄ SP1',                    'ergebnis'],
            'summe_vz_sp2'          => ['VZÄ SP2',                    'ergebnis'],
        ];

        foreach ($fields as $key => [$label, $type]) {
            $row    = array_fill(0, 1 + $anzahl * 2, '');
            $row[0] = $label;
            foreach ($this->monate as $idx => $monat) {
                $b           = $byMonat[$monat]['berechnungen'] ?? [];
                $val         = $b[$key] ?? '';
                $row[1 + $idx * 2] = $val;
                $row[2 + $idx * 2] = '';
            }
            $rows[]           = $row;
            $this->rowTypes[] = $type;
        }

        return $rows;
    }

    // ── Spaltenbreiten ───────────────────────────────────────────────

    public function columnWidths(): array
    {
        $widths = ['A' => 32];
        $anzahl = count($this->monate);
        for ($i = 0; $i < $anzahl; $i++) {
            $sp1 = static::colLetter($this->sp1Col($i));
            $sp2 = static::colLetter($this->sp2Col($i));
            $widths[$sp1] = 12;
            $widths[$sp2] = 12;
        }
        return $widths;
    }

    // ── Styles ──────────────────────────────────────────────────────

    public function styles(Worksheet $sheet): array
    {
        $anzahl   = count($this->monate);
        $lastCol  = static::colLetter(1 + $anzahl * 2);
        $totalRows = count($this->rowTypes);

        // Basis: alle Zellen, kleiner Font, kein Wrap
        $sheet->getStyle("A1:{$lastCol}{$totalRows}")->applyFromArray([
            'font'      => ['name' => 'Calibri', 'size' => 9],
            'alignment' => ['wrapText' => false],
            'borders'   => [
                'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        foreach ($this->rowTypes as $rowIdx => $type) {
            $rowNum = $rowIdx + 1;
            $range  = "A{$rowNum}:{$lastCol}{$rowNum}";

            match ($type) {
                'info' => $sheet->getStyle($range)->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '4C1D95']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_SNAPSHOT]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]),
                'monat_header' => $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_HEADER]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]),
                'sp_header' => $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 8],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]),
                'parameter' => $sheet->getStyle($range)->applyFromArray([
                    'font' => ['italic' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_PARAM]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]),
                'person' => $sheet->getStyle($range)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_PERSON]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]),
                'summe' => $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_SUMME]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]),
                'ergebnis' => $sheet->getStyle($range)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_ERGEBNIS]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]),
                'delta' => $this->applyDeltaStyles($sheet, $rowNum, $lastCol, $anzahl),
                default => null,
            };

            // Erste Spalte immer linksbündig
            $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Monat-Header mergen (je 2 Spalten)
        foreach ($this->monate as $idx => $monat) {
            $c1 = static::colLetter($this->sp1Col($idx));
            $c2 = static::colLetter($this->sp2Col($idx));
            $sheet->mergeCells("{$c1}2:{$c2}2");
        }

        // Zeilen 1 über alle Spalten mergen
        $sheet->mergeCells("A1:{$lastCol}1");

        // Zeilenhöhen
        $sheet->getRowDimension(1)->setRowHeight(16);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getRowDimension(3)->setRowHeight(14);
        $sheet->freezePane('B4');

        return [];
    }

    private function applyDeltaStyles(Worksheet $sheet, int $rowNum, string $lastCol, int $anzahl): null
    {
        $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ($this->monate as $idx => $monat) {
            $col = static::colLetter($this->sp1Col($idx));
            $val = $sheet->getCell("{$col}{$rowNum}")->getValue();
            if (is_numeric($val)) {
                $color = $val >= 0 ? self::C_POSITIV : self::C_NEGATIV;
                $sheet->getStyle("{$col}{$rowNum}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                ]);
            }
        }
        return null;
    }
}

