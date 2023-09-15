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
        if (auth()->user()->can('view absences')){
            $absences = Absence::whereDate('end', '>=', Carbon::now()->startOfDay())->orderBy('start')->get();
            $oldAbsences = Absence::whereDate('end', '<', Carbon::now()->startOfDay())->orderByDesc('end')->paginate(5);
        } else {
            $absences = [];
            $oldAbsences = [];
        }


        $view->with([
            'absences' => $absences,
            'oldAbsences' => $oldAbsences
        ]);
    }
}
