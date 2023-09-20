<?php

namespace App\View\Composers;

use App\Models\personal\TimesheetDays;
use Carbon\Carbon;
use Illuminate\View\View;

class TimeRecordingCardOwnComposer
{
    /**
     *
     */
    public function __construct()
    {

    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $days = [];

        $timesheetDays = auth()->user()->timesheet_days()->whereBetween('date', [Carbon::now()->startOfWeek()->format('Y-m-d'), Carbon::now()->endOfWeek()->format('Y-m-d')])->get();

        foreach ($timesheetDays as $timesheetDay) {
            $days[$timesheetDay->date->format('Y-m-d')][] = $timesheetDay;
        }

        $view->with([
            'days' => $days,
        ]);
    }
}
