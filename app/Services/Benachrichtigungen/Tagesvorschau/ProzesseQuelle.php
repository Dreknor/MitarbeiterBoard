<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\Procedure_Step;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eigene offene Prozessschritte, die bis zwei Tage nach dem Zieltag fällig sind.
 */
class ProzesseQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'prozesse';
    }

    public function label(): string
    {
        return 'Prozessschritte';
    }

    public function icon(): string
    {
        return 'fa-project-diagram';
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        // Wie ProcedureComposer
        return $user->steps()
            ->where('done', false)
            ->whereNotNull('endDate')
            ->whereDate('endDate', '<=', $tag->copy()->addDays(2))
            ->whereHas('procedure', fn (Builder $q) => $q->laufend())
            ->with('procedure')
            ->orderBy('endDate')
            ->get()
            ->map(fn (Procedure_Step $step) => new TagesvorschauEintrag(
                titel: (string) $step->name,
                zeit: 'bis '.$step->endDate->format('d.m.'),
                details: $step->procedure?->name,
                url: $step->procedure ? url('procedure/'.$step->procedure->id.'/start') : null,
                hervorheben: $step->endDate->lt($tag->copy()->startOfDay()),
            ));
    }
}
