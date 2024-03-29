<?php

namespace App\View\Composers;

use App\Models\personal\TimesheetDays;
use Carbon\Carbon;
use Illuminate\View\View;

class TimeRecordingCardComposer
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
        $users = [];

        $timesheetDay = TimesheetDays::query()->whereDate('date', Carbon::now()->format('Y-m-d'))->whereNull('end')->get();

        foreach ($timesheetDay as $day) {
            $users[$day->employe->name] = (!is_null($day->start))? $day->start?->format('H:i') : $day->comment ;
        }

        $view->with('users', $users);
    }
}
