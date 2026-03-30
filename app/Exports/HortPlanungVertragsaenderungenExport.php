<?php

namespace App\Exports;

use App\Models\personal\HortPlanung;
use App\Services\HortPlanungService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel-Export der zu ändernden Verträge einer Hortstunden-Planung.
 *
 * Zeigt pro Person alle Monate, in denen sich SP1 oder SP2 gegenüber
 * dem Vormonat ändert. Spalten: Person, Ab Monat, SP1 (vorher → neu),
 * SP2 (vorher → neu), Zusatzstunden-Anteil, Gesamtwert SP1, Vertrag, Differenz.
 */
class HortPlanungVertragsaenderungenExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected int $headerRow = 1;
    protected int $dataRows  = 0;

    public function __construct(
        protected HortPlanung $planung,
        protected HortPlanungService $service
    ) {
        $this->planung->load([
            'faktoren.werte',
            'monate.personen.user',
            'monate.monatZusatzstunden.typ',
            'department',
        ]);
    }

    public function array(): array
    {
        $aenderungen = $this->service->berechneVertragsaenderungen($this->planung);

        if ($aenderungen->isEmpty()) {
            return [['Keine Vertragsänderungen nötig – alle SP1/SP2-Werte sind durchgehend konstant.']];
        }

        $rows = [];

        // Header
        $rows[] = [
            'Person',
            'Ab Monat',
            'SP1 vorher (h)',
            'SP1 neu (h)',
            'SP2 vorher (h)',
            'SP2 neu (h)',
            'Zusatzstd. (h)',
            'Gesamt SP1 (h)',
            'Vertrag (h)',
            'Δ SP1–Vertrag (h)',
        ];

        $fmt = fn(?float $v): string =>
            $v !== null ? number_format($v, 2, ',', '.') : '–';

        foreach ($aenderungen as $userId => $personAenderungen) {
            foreach ($personAenderungen as $a) {
                $rows[] = [
                    $a['user_name'],
                    $a['monat_label'],
                    $fmt($a['sp1_vorher']),
                    $fmt($a['sp1']),
                    $fmt($a['sp2_vorher']),
                    $fmt($a['sp2']),
                    $fmt($a['zusatzstunden']),
                    $fmt($a['gesamtwert_sp1']),
                    $fmt($a['vertrag']),
                    $fmt($a['differenz']),
                ];
                $this->dataRows++;
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            // Header-Zeile
            'A1:J1' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
            ],
        ];

        // Datenzeilen: Differenz-Spalte (J) bedingt einfärben
        for ($row = 2; $row <= $this->dataRows + 1; $row++) {
            $val = $sheet->getCell("J{$row}")->getValue();
            if ($val !== null && $val !== '–' && $val !== '0,00') {
                $styles["J{$row}"] = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'B45309']],
                ];
            }
        }

        return $styles;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24, 'B' => 18, 'C' => 14, 'D' => 14,
            'E' => 14, 'F' => 14, 'G' => 14, 'H' => 16,
            'I' => 14, 'J' => 18,
        ];
    }

    public function title(): string
    {
        return 'Vertragsänderungen';
    }
}

