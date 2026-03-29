<?php

namespace App\Exports\Sheets;

use App\Models\personal\HortPlanung;
use App\Services\HortPlanungService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * „Grundarbeitszeit"-Sheet: VZ-Anteil und Stunden-Aufschlüsselung pro Person.
 * Analog zum gleichnamigen Sheet in der Excel-Vorlage (§2.3).
 */
class HortPlanungGrundarbeitszeitSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        protected HortPlanung $planung,
        protected HortPlanungService $service
    ) {}

    public function array(): array
    {
        $rows = [];

        // Header
        $rows[] = ['Person', 'VZ-Anteil', 'Wochenstunden', 'Erzieher VZ', 'Leitungs VZ', 'Vorbereitungs VZ', 'Anpassungs VZ', 'Sonstige'];

        $monate  = $this->planung->monate;
        $personen = $this->planung->monate
            ->flatMap(fn($m) => $m->personen)
            ->unique('user_id')
            ->sortBy(fn($p) => $p->user?->name ?? 'zzz');

        // Aktuellsten Monat als Referenz für die Grundarbeitszeit-Berechnung nehmen
        $refMonat = $monate
            ->filter(fn($m) => $m->monat->lessThanOrEqualTo(now()))
            ->sortByDesc('monat')
            ->first() ?? $monate->first();

        if (!$refMonat) {
            return $rows;
        }

        foreach ($personen as $person) {
            // Person im Referenzmonat suchen
            $refPerson = $refMonat->personen->firstWhere('user_id', $person->user_id);
            if (!$refPerson) {
                continue;
            }

            try {
                $aufschluesselung = $this->service->berechneGrundarbeitszeit($refPerson);
            } catch (\Throwable) {
                continue;
            }

            $row = [
                $person->user?->name ?? '–',
                number_format((float) ($aufschluesselung['vz_anteil'] ?? 0), 4, ',', '.'),
                number_format((float) ($aufschluesselung['wochenstunden'] ?? 0), 2, ',', '.'),
                number_format((float) ($aufschluesselung['erzieher_vz'] ?? 0), 4, ',', '.'),
                number_format((float) ($aufschluesselung['leitung_vz'] ?? 0), 4, ',', '.'),
                number_format((float) ($aufschluesselung['vorbereitung_vz'] ?? 0), 4, ',', '.'),
                number_format((float) ($aufschluesselung['anpassung_vz'] ?? 0), 4, ',', '.'),
                number_format((float) ($aufschluesselung['zusatzstunden'] ?? 0), 2, ',', '.'),
            ];

            $rows[] = $row;
        }

        // Leerzeile + Hinweis
        $rows[] = [];
        $rows[] = ['Referenzmonat: ' . ($refMonat ? $refMonat->monat->translatedFormat('F Y') : '–'), '', '', '', '', '', '', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            'A1:H1' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24, 'B' => 12, 'C' => 16,
            'D' => 14, 'E' => 14, 'F' => 18, 'G' => 16, 'H' => 12,
        ];
    }

    public function title(): string
    {
        return 'Grundarbeitszeit';
    }
}

