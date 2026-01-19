<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class SickNotesUserSummaryExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    private Int $rows = 0;
    private Collection $users;

    public function __construct(Collection $users)
    {
        $this->users = $users;
    }

    public function map($row): array
    {
        return [
            ++$this->rows,
            $row['user'],
            $row['with_note'],
            $row['without_note'],
            $row['missing_note'],
            $row['with_note'] + $row['without_note'] + $row['missing_note'],
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Mitarbeiter',
            'Tage mit Schein',
            'Tage ohne Schein',
            'Tage fehlt Schein',
            'Gesamt Tage'
        ];
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->users;
    }

    public function title(): string
    {
        return 'Mitarbeiter-Übersicht';
    }
}
