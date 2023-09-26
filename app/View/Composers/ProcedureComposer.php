<?php

namespace App\View\Composers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProcedureComposer
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
        $view->with(['steps' =>  auth()->user()->steps()->where('done', 0)->whereNotNull('endDate')->get()]);
    }
}
