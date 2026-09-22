<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SickNotesCompleteExport implements WithMultipleSheets
{
    private $absences;
    private $users;
    private $shortSickNotesData;

    public function __construct($absences, $users, $shortSickNotesData = null)
    {
        $this->absences = $absences;
        $this->users = $users;
        $this->shortSickNotesData = $shortSickNotesData;
    }

    public function sheets(): array
    {
        $sheets = [
            new SickNotesExport($this->absences),
            new SickNotesUserSummaryExport($this->users),
        ];

        if ($this->shortSickNotesData) {
            $sheets[] = new ShortSickNotesWithoutCertificateExport($this->shortSickNotesData);
        }

        return $sheets;
    }
}
