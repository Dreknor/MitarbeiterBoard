<?php

namespace App\Exports;

use App\Models\Absence;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AbsenceExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting
{
    private Int $rows = 0;

    public function map($row): array
    {
        return [
            ++$this->rows,
            $row->user->name,
            $row->start->format('d.m.Y'),
            $row->end->format('d.m.Y'),
            $row->reason
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Mitarbeiter',
            'von',
            'bis',
            'Grund'
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Absence::whereHas('user')->get();

    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'D' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }
}
