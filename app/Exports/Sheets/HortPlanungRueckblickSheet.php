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
 * „Rückblick"-Sheet: Soll / Vertrag / Ist-Stunden vergangener Monate.
 * Zeigt Abweichungen und VZÄ-Zusammenfassung.
 */
class HortPlanungRueckblickSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        protected HortPlanung $planung,
        protected HortPlanungService $service
    ) {}

    public function array(): array
    {
        $rows  = [];
        $heute = now()->startOfMonth();

        $vergMonate = $this->planung->monate
            ->filter(fn($m) => $m->monat->lessThan($heute))
            ->sortBy('monat')
            ->values();

        if ($vergMonate->isEmpty()) {
            return [['Keine vergangenen Monate vorhanden.']];
        }

        // ── Haupttabelle: Person × Monat ──────────────────────────────
        // Header
        $header = ['Person', 'Monat', 'Soll SP1', 'Soll SP2', 'Vertrag', 'Ist', 'Abw. Soll–Vertrag', 'Abw. Soll–Ist'];
        $rows[] = $header;

        foreach ($vergMonate as $monat) {
            foreach ($monat->personen->sortBy(fn($p) => $p->user?->name) as $person) {
                $abwVertrag = ($person->stunden_gesamt ?? 0) - ($person->stunden_vertrag ?? 0);
                $abwIst     = ($person->stunden_gesamt ?? 0) - ($person->stunden_ist ?? 0);

                $rows[] = [
                    $person->user?->name ?? '–',
                    $monat->monat->translatedFormat('F Y'),
                    number_format((float) ($person->stunden_gesamt ?? 0), 2, ',', '.'),
                    number_format((float) ($person->stunden_stadt ?? 0), 2, ',', '.'),
                    number_format((float) ($person->stunden_vertrag ?? 0), 2, ',', '.'),
                    number_format((float) ($person->stunden_ist ?? 0), 2, ',', '.'),
                    number_format($abwVertrag, 2, ',', '.'),
                    number_format($abwIst, 2, ',', '.'),
                ];
            }
        }

        // Leerzeile
        $rows[] = [];

        // ── Zusammenfassung: Monatsaggregation ────────────────────────
        $rows[] = ['Monat', 'Σ Soll SP1', 'Σ Soll SP2', 'VZÄ SP1', 'VZÄ SP2', 'VZÄ gesetzl.', 'Budget-Rest', 'Differenz Stadt'];

        foreach ($vergMonate as $monat) {
            $b = $this->service->berechneMonat($monat);
            $rows[] = [
                $monat->monat->translatedFormat('F Y'),
                number_format($b['summe_sp1'], 2, ',', '.'),
                number_format($b['summe_sp2'], 2, ',', '.'),
                number_format($b['summe_vz_sp1'], 4, ',', '.'),
                number_format($b['summe_vz_sp2'], 4, ',', '.'),
                number_format($b['summe_gesetz_vz'], 4, ',', '.'),
                number_format($b['budget_rest_sp1'], 2, ',', '.'),
                number_format($b['differenz_stadt'], 2, ',', '.'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Personen-Tabelle Header
            'A1:H1' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24, 'B' => 16, 'C' => 12, 'D' => 12,
            'E' => 12, 'F' => 12, 'G' => 18, 'H' => 16,
        ];
    }

    public function title(): string
    {
        return 'Rückblick';
    }
}

