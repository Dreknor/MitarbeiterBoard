<?php

namespace App\Exports;

use App\Exports\Sheets\WeekTableSheet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PaedDiaryWeekTableExport implements WithMultipleSheets
{
    protected Collection $klassen;
    protected Carbon $weekStart;
    protected Carbon $weekEnd;

    public function __construct(Collection $klassen, Carbon $weekStart, Carbon $weekEnd)
    {
        $this->klassen   = $klassen;
        $this->weekStart = $weekStart;
        $this->weekEnd   = $weekEnd;
    }

    public function sheets(): array
    {
        return [
            new WeekTableSheet($this->klassen, $this->weekStart, $this->weekEnd),
        ];
    }
}

