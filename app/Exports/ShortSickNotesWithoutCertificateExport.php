<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ShortSickNotesWithoutCertificateExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    private int $rows = 0;
    private Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function map($row): array
    {
        return [
            ++$this->rows,
            $row['user'],
            $row['count'],
            $row['total_days'],
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Mitarbeiter',
            'Anzahl Abwesenheiten',
            'Summe Tage',
        ];
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->data;
    }

    public function title(): string
    {
        return 'Kurze Krankm. ohne Schein';
    }
}
