<?php

namespace App\Exports\Sheets;

use App\Models\personal\HortPlanung;
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
 * „Schlüssel"-Sheet: Faktor-Definitionen mit aktuellen und historischen Werten.
 */
class HortPlanungSchluesselSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(protected HortPlanung $planung) {}

    public function array(): array
    {
        $rows = [];

        // Header
        $rows[] = ['Kürzel', 'Bezeichnung', 'Berechnungstyp', 'Position', 'Aktiv', 'Gesetzl. Grundlage', 'Gültig ab', 'Wert', 'Notiz'];

        $faktoren = $this->planung->faktoren->sortBy('position');

        foreach ($faktoren as $faktor) {
            foreach ($faktor->werte as $wert) {
                $rows[] = [
                    $faktor->kuerzel,
                    $faktor->bezeichnung,
                    $faktor->berechnungs_typ,
                    $faktor->position,
                    $faktor->aktiv ? 'Ja' : 'Nein',
                    $faktor->gesetzliche_grundlage ?? '',
                    $wert->gueltig_ab instanceof Carbon
                        ? $wert->gueltig_ab->format('d.m.Y')
                        : $wert->gueltig_ab,
                    number_format((float) $wert->wert, 6, ',', '.'),
                    $wert->notiz ?? '',
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, 'B' => 22, 'C' => 18, 'D' => 10,
            'E' => 8,  'F' => 26, 'G' => 14, 'H' => 14, 'I' => 30,
        ];
    }

    public function title(): string
    {
        return 'Schlüssel';
    }
}

