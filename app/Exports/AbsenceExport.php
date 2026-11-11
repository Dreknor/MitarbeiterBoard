<?php

namespace App\Exports;

use App\Models\Absence;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel-Export der Abwesenheiten. Bekommt die (gefilterte) Liste aus dem AbsenceController,
 * ohne Übergabe werden alle Abwesenheiten exportiert.
 */
class AbsenceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    private int $rows = 0;

    public function __construct(private readonly ?Collection $absences = null)
    {
    }

    /**
     * @param Absence $row
     */
    public function map($row): array
    {
        return [
            ++$this->rows,
            $row->user->name,
            $row->start->format('d.m.Y'),
            $row->end->format('d.m.Y'),
            $row->days,
            $row->reason,
            $row->showVertretungsplan ? 'ja' : 'nein',
            $row->holiday_id !== null ? 'ja' : 'nein',
            $row->creator->name,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Mitarbeiter',
            'von',
            'bis',
            'Arbeitstage',
            'Grund',
            'Vertretungsplan',
            'aus Urlaub',
            'eingetragen von',
        ];
    }

    public function collection(): Collection
    {
        return $this->absences
            ?? Absence::query()->with(['user', 'creator'])->orderByDesc('start')->get();
    }
}
