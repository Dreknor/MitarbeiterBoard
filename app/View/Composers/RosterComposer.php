<?php

namespace App\View\Composers;

use App\Models\personal\Roster;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RosterComposer
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

        if (auth()->user()->can('create roster')) {
            $view->with('rosters', Roster::whereIn('department_id', auth()->user()->groups()->pluck('id'))
                ->whereDate('start_date', '>=' ,Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->where('type', '!=', 'template')

                ->get());
        } else {
            $view->with('rosters', Roster::whereIn('department_id', auth()->user()->groups()->pluck('id'))
                ->whereDate('start_date', '>=' ,Carbon::now()->startOfWeek()->format('Y-m-d'))
                ->where('type', '!=', 'template')
                ->where('published', true)
                ->get());
        }

        //aktuelle Woche
        $roster = Roster::whereIn('department_id', auth()->user()->groups()->pluck('id'))
            ->whereDate('start_date', '=' ,Carbon::now()->startOfWeek()->format('Y-m-d'))
            ->where('type', '!=', 'template')
            ->where('published', true)
            ->first();
        $working_times = collect([]);
        if ($roster)
        {
            //Arbeitszeiten der aktuellen Woche
            $working_times = $roster->working_times_day(Carbon::today());
        }
        $view->with('working_times', $working_times);
    }
}
