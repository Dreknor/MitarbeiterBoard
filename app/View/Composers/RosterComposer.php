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
        $view->with('rosters', Roster::whereIn('department_id', auth()->user()->groups()->pluck('id'))
            ->whereDate('start_date', '>=' ,Carbon::now()->startOfWeek()->format('Y-m-d'))
            ->where('type', '!=', 'template')
            ->get());
    }
}
