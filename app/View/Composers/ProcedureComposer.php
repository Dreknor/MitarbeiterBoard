<?php

namespace App\View\Composers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ProcedureComposer
{
    /**
     * Offene, bereits fällig gestellte Schritte des Nutzers aus laufenden Prozessen –
     * dringendste zuerst. Schritte gelöschter/beendeter Prozesse werden ausgeblendet.
     */
    public function compose(View $view): void
    {
        $steps = auth()->user()->steps()
            ->where('done', false)
            ->whereNotNull('endDate')
            ->whereHas('procedure', fn (Builder $q) => $q->laufend())
            ->with('procedure')
            ->orderBy('endDate')
            ->get();

        $view->with(['steps' => $steps]);
    }
}
