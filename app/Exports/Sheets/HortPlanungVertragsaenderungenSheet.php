<?php

namespace App\Exports\Sheets;

use App\Models\personal\HortPlanung;
use App\Services\HortPlanungService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * „Vertragsänderungen"-Sheet: Wann müssen bei welcher Person die
 * vertraglich vereinbarten Stunden angepasst werden?
 *
 * Zusatzstunden = SP1 − SP2 (Stunden über den Stundenhort hinaus).
 * Beispiel: SP2 = 22 h, SP1 = 29 h → Stundenhort 22 + 7 Zusatz = 29 h
 */
class HortPlanungVertragsaenderungenSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        protected HortPlanung $planung,
        protected HortPlanungService $service
    ) {}

    public function array(): array
    {
        $aenderungen = $this->service->berechneVertragsaenderungen($this->planung);

        if ($aenderungen->isEmpty()) {
            return [['Keine Vertragsänderungen – SP1/SP2-Werte durchgehend konstant.']];
        }

        $rows = [];

        // Header
        $rows[] = [
            'Person',
            'Ab Monat',
            'Geändert',
            'Stundenhort SP2 (h)',
            'Zusatzstunden (h)',
            'Gesamt SP1 (h)',
            'SP1 vorher (h)',
            'SP2 vorher (h)',
            'Vertrag (h)',
        ];

        $fmt = fn(?float $v): string =>
            $v !== null ? number_format($v, 2, ',', '.') : '–';

        foreach ($aenderungen as $userId => $personAenderungen) {
            foreach ($personAenderungen as $a) {
                $aenderungsTyp = match (true) {
                    $a['sp1_geaendert'] && $a['sp2_geaendert'] => 'SP1+SP2',
                    $a['sp1_geaendert']                         => 'SP1',
                    $a['sp2_geaendert']                         => 'SP2',
                    default                                     => '–',
                };

                $rows[] = [
                    $a['user_name'],
                    $a['monat_label'],
                    $aenderungsTyp,
                    $fmt($a['sp2']),
                    $fmt($a['zusatzstunden']),
                    $fmt($a['sp1']),
                    $fmt($a['sp1_vorher']),
                    $fmt($a['sp2_vorher']),
                    $fmt($a['vertrag']),
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            'A1:I1' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24, 'B' => 18, 'C' => 12, 'D' => 20,
            'E' => 18, 'F' => 16, 'G' => 16, 'H' => 16,
            'I' => 14,
        ];
    }

    public function title(): string
    {
        return 'Vertragsänderungen';
    }
}
