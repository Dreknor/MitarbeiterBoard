<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Personenübersicht aus KrankmeldungService::personen() (Tage = Arbeitstage im Zeitraum).
 */
class SickNotesUserSummaryExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    private int $rows = 0;

    public function __construct(private readonly Collection $personen)
    {
    }

    public function map($row): array
    {
        return [
            ++$this->rows,
            $row['name'],
            $row['faelle'],
            $row['tage'],
            $row['liegt_vor'],
            $row['nicht_noetig'],
            $row['fehlt'],
            $row['ausstehend'],
            $row['kurz'],
            $row['laengste'],
            $row['letzte']->format('d.m.Y'),
            $row['bem'] !== null ? 'prüfen ('.$row['bem'].' Arbeitstage in 12 Monaten)' : '',
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Mitarbeiter',
            'Krankmeldungen',
            'Tage gesamt',
            'Tage mit Schein',
            'Tage ohne Schein (nicht nötig)',
            'Tage Schein fehlt',
            'Tage Schein noch nicht fällig',
            'Kurzerkrankungen ohne Schein',
            'Längste (Arbeitstage)',
            'Letzte Krankmeldung',
            'BEM-Hinweis',
        ];
    }

    public function collection(): Collection
    {
        return $this->personen;
    }

    public function title(): string
    {
        return 'Personen';
    }
}
