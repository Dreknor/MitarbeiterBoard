<?php

namespace App\View\Composers;

use App\Models\personal\WorkingTime;
use App\Models\WikiSite;
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

        $working_times = WorkingTime::query()->whereDate('start', Carbon::now()->format('Y-m-d'))->whereNull('end')->get();

        foreach ($working_times as $working_time) {

            $users[$working_time->employe->name] = $working_time->start->format('H:i');;
        }

        $view->with('users', $users);
    }
}
