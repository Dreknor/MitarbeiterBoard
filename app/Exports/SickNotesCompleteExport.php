<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SickNotesCompleteExport implements WithMultipleSheets
{
    private $absences;
    private $users;

    public function __construct($absences, $users)
    {
        $this->absences = $absences;
        $this->users = $users;
    }

    public function sheets(): array
    {
        return [
            new SickNotesExport($this->absences),
            new SickNotesUserSummaryExport($this->users),
        ];
    }
}
