<?php

namespace App\View\Composers;

use App\Models\Absence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AbsenceComposer
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
        $absences = Absence::whereDate('end', '>=', Carbon::now()->startOfDay())->orderBy('start')->with('user')->get();
        $view->with([
            'absences' => $absences,
        ]);
    }
}
