<?php

namespace App\Exports;

use App\Models\Absence;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SickNotesExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithTitle
{
    private Int $rows = 0;
    private Collection $absences;

    public function __construct(Collection $absences)
    {
        $this->absences = $absences;
    }

    public function map($row): array
    {
        return [
            ++$this->rows,
            $row->user->name,
            $row->reason,
            $row->start->format('d.m.Y'),
            $row->end->format('d.m.Y'),
            $row->days,
            $row->sick_note_date ? $row->sick_note_date->format('d.m.Y') :
                (($row->sick_note_required or $row->days >= settings('absence_sick_note_days', 'absences')) ? 'Benötigt' : '-'),
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Mitarbeiter',
            'Grund',
            'Von',
            'Bis',
            'Dauer (Tage)',
            'Krankenschein'
        ];
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->absences;
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'E' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'G' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function title(): string
    {
        return 'Krankmeldungen';
    }
}
