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

/**
 * Krankmeldungen einer Person (/sick_notes/export/user/{user}) im gewählten Zeitraum.
 */
class SickNotesByUserExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    private int $rows = 0;

    public function __construct(
        private readonly KrankmeldungService $service,
        private readonly Collection $absences,
        private readonly string $userName,
    ) {
    }

    /**
     * @param Absence $row
     */
    public function map($row): array
    {
        return [
            ++$this->rows,
            $row->reason,
            $row->start->format('d.m.Y'),
            $row->end->format('d.m.Y'),
            $this->service->kalendertage($row),
            $this->service->arbeitstage($row),
            KrankmeldungService::STATUS[$this->service->status($row)],
            $row->sick_note_date?->format('d.m.Y') ?? '',
        ];
    }

    public function headings(): array
    {
        return ['#', 'Grund', 'Von', 'Bis', 'Kalendertage', 'Arbeitstage', 'Krankenschein', 'Schein erfasst am'];
    }

    public function collection(): Collection
    {
        return $this->absences;
    }

    public function title(): string
    {
        // Excel: max. 31 Zeichen, keine Sonderzeichen : \ / ? * [ ]
        return KrankmeldungService::sichererName($this->userName);
    }
}
