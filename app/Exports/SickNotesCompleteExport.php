<?php

namespace App\Exports;

use App\Services\KrankmeldungService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Krankmeldungen-Export (/sick_notes/export): Liste, Personenübersicht und Statistik –
 * mit denselben Filtern und Regeln wie die Seite.
 */
class SickNotesCompleteExport implements WithMultipleSheets
{
    public function __construct(
        private readonly KrankmeldungService $service,
        private readonly array $filter,
        private readonly Collection $meldungen,
    ) {
    }

    public function sheets(): array
    {
        return [
            new SickNotesExport($this->service, $this->service->liste($this->meldungen, $this->filter), $this->filter),
            new SickNotesUserSummaryExport($this->service->personen($this->meldungen, $this->filter)),
            new SickNotesStatistikExport($this->service, $this->meldungen, $this->filter),
        ];
    }
}
