<?php

namespace App\View\Composers;

use App\Enums\QualificationStatus;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\QualificationType;
use App\Services\Personal\PersonalScopeService;
use Illuminate\View\View;

class MissingQualificationsComposer
{
    public function compose(View $view): void
    {
        if (! auth()->user()?->can('manage qualifications')) return;

        // Ablaufende oder abgelaufene Qualifikationen
        $count = EmployeeQualification::whereIn('status', [
            QualificationStatus::Ablaufend->value,
            QualificationStatus::Abgelaufen->value,
        ])->count();

        $view->with('missingQualificationsCount', $count);
    }
}

