<?php

namespace App\View\Composers;

use App\Models\Absence;
use App\Models\Vertretung;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class VertretungenComposer
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
        if (!auth()->user()->can('view vertretungen')) {
            $vertretungen = auth()->user()->vertretungen()->whereDate('date', '>=', \Carbon\Carbon::today())->orderBy('date')->orderBy('stunde')->get();
        } else {
            $vertretungen = Vertretung::whereDate('date', '>=', \Carbon\Carbon::today())->orderBy('date')->orderBy('stunde')->get();
        }

        $view->with([
            'vertretungen' => $vertretungen,
        ]);
    }
}
