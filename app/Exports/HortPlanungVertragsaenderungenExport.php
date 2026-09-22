<?php

namespace App\Exports;

use App\Models\personal\HortPlanung;
use App\Services\HortPlanungService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel-Export der zu ändernden Verträge einer Hortstunden-Planung.
 *
 * Zeigt pro Person alle Monate, in denen sich SP1 oder SP2 gegenüber
 * dem Vormonat ändert. Zusatzstunden = SP1 − SP2 (Stunden über den
 * Stundenhort hinaus).
 *
 * Beispiel: SP2 = 22 h, SP1 = 29 h → Stundenhort 22 h + 7 h Zusatz = 29 h
 */
class HortPlanungVertragsaenderungenExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected int $dataRows = 0;

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
                $this->dataRows++;
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header-Zeile
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
