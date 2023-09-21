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

        $timesheet_day = $timesheetDays->filter(function ($day){
            if (is_null($day->end) and $day->date->isToday() and $day->percent_of_workingtime == 0){
                return $day;
            }
        })->first();

        if (!is_null($timesheet_day)){
            $logout = 1;
        } else {
            $logout = 0;
        }

        $view->with([
            'days' => $days,
            'logout' => $logout
        ]);
    }
}
