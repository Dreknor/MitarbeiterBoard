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
 * Erkennt Übergänge: SP1 und/oder SP2 können sich unabhängig
 * voneinander ändern – beide Werte werden einzeln verglichen.
 */
class HortPlanungVertragsaenderungenSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected int $dataRows = 0;

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
            'SP1 vorher (h)',
            'SP1 neu (h)',
            'SP2 vorher (h)',
            'SP2 neu (h)',
            'Zusatzstd. (h)',
            'Gesamt SP1 (h)',
            'Vertrag (h)',
            'Δ SP1–Vertrag (h)',
            'Geändert',
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
                    $fmt($a['sp1_vorher']),
                    $fmt($a['sp1']),
                    $fmt($a['sp2_vorher']),
                    $fmt($a['sp2']),
                    $fmt($a['zusatzstunden']),
                    $fmt($a['gesamtwert_sp1']),
                    $fmt($a['vertrag']),
                    $fmt($a['differenz']),
                    $aenderungsTyp,
                ];
                $this->dataRows++;
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header-Zeile
            'A1:K1' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24, 'B' => 18, 'C' => 14, 'D' => 14,
            'E' => 14, 'F' => 14, 'G' => 14, 'H' => 16,
            'I' => 14, 'J' => 18, 'K' => 12,
        ];
    }

    public function title(): string
    {
        return 'Vertragsänderungen';
    }
}

