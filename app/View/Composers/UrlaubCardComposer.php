<?php

namespace App\View\Composers;

use App\Models\personal\Holiday;
use App\Models\personal\TimesheetDays;
use Carbon\Carbon;
use Illuminate\View\View;

class UrlaubCardComposer
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
        $unapproved = [];

        if (auth()->user()->can('approve holidays')) {
            $unapproved = Holiday::where('approved', false)->get();
        }

        $view->with([
            'unapproved' => $unapproved,
            'holidays' => Holiday::where('employe_id', auth()->id())
                ->where('start_date', '>=', Carbon::today())
                ->orderBy('start_date', 'asc')
                ->get(),
        ]);
    }
}
