<?php

namespace App\View\Composers;

use App\Models\Absence;
use App\Services\AbsenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AbsenceComposer
{
    public function __construct(private readonly AbsenceService $service)
    {
    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $absences = Absence::whereDate('end', '>=', Carbon::now()->startOfDay())->orderBy('start')->with('user')->get();
        $user = auth()->user();
        $view->with([
            'absences' => $absences,
            'absenceService' => $this->service,
            'meldbarePersonen' => $user && $this->service->darfFuerAndereMelden($user)
                ? $this->service->meldbarePersonen($user)
                : collect(),
        ]);
    }
}
