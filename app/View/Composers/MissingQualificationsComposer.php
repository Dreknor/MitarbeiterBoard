<?php

namespace App\View\Composers;

use App\Enums\QualificationStatus;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\QualificationType;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\View\View;

class MissingQualificationsComposer
{
    public function compose(View $view): void
    {
        if (! auth()->user()?->can('manage qualifications')) return;

        // Ablaufende oder abgelaufene Qualifikationen
        $expiringCount = EmployeeQualification::whereIn('status', [
            QualificationStatus::Ablaufend->value,
            QualificationStatus::Abgelaufen->value,
        ])->count();

        // Wirklich fehlende Pflichtqualifikationen (Typ existiert, aber kein gültiger Eintrag)
        $requiredTypes = QualificationType::where('category', 'pflicht')
            ->where('is_active', true)
            ->get();

        $activeEmployees = app(PersonalScopeService::class)->visibleEmployees(auth()->user())->get();

        $missingCount = 0;
        foreach ($activeEmployees as $employe) {
            foreach ($requiredTypes as $qt) {
                // Prüfen ob applies_to passt
                if ($qt->applies_to !== null) {
                    $employeTypes = $employe->employments()
                        ->where('status', 'aktiv')
                        ->pluck('employment_type')
                        ->map(fn($t) => is_string($t) ? $t : $t->value)
                        ->toArray();
                    if (empty(array_intersect($qt->applies_to, $employeTypes))) continue;
                }

                $hasValid = $employe->qualifications()
                    ->where('qualification_type_id', $qt->id)
                    ->whereNotIn('status', [QualificationStatus::Fehlend->value, QualificationStatus::Abgelaufen->value])
                    ->exists();

                if (! $hasValid) {
                    $missingCount++;
                }
            }
        }

        $view->with('missingQualificationsCount', $expiringCount + $missingCount);
    }
}

