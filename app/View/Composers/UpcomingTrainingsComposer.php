<?php

namespace App\View\Composers;

use App\Enums\TrainingStatus;
use App\Models\personal\Training;
use Illuminate\View\View;

class UpcomingTrainingsComposer
{
    public function compose(View $view): void
    {
        if (! auth()->user()?->can('view trainings')) return;

        $count = Training::where('status', '!=', TrainingStatus::Abgesagt->value)
            ->where('end_date', '>=', now())
            ->where('start_date', '<=', now()->addDays(30))
            ->count();

        $view->with('upcomingTrainingsCount', $count);
    }
}

