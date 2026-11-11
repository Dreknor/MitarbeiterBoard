<?php

namespace App\Exports;

use App\Models\Absence;
use App\Services\KrankmeldungService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class SickNotesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    private int $rows = 0;

    public function __construct(
        private readonly KrankmeldungService $service,
        private readonly Collection $absences,
        private readonly array $filter,
    ) {
    }

    /**
     * @param Absence $row
     */
    public function map($row): array
    {
        return [
            ++$this->rows,
            $row->user->name,
            $row->reason,
            $row->start->format('d.m.Y'),
            $row->end->format('d.m.Y'),
            $this->service->kalendertage($row),
            $this->service->arbeitstage($row),
            $this->service->arbeitstage($row, $this->filter['von'], $this->filter['bis']),
            KrankmeldungService::STATUS[$this->service->status($row)],
            $row->sick_note_date?->format('d.m.Y') ?? '',
            $this->service->scheinNoetig($row) ? $this->service->faelligAb($row)->format('d.m.Y') : '',
            $this->service->istKurzerkrankung($row) ? 'ja' : 'nein',
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
            'Kalendertage',
            'Arbeitstage',
            'Arbeitstage im Zeitraum',
            'Krankenschein',
            'Schein erfasst am',
            'Schein fällig ab',
            'Kurzerkrankung ohne Schein',
        ];
    }

    public function collection(): Collection
    {
        return $this->absences;
    }

    public function title(): string
    {
        return 'Krankmeldungen';
    }
}
