<?php

namespace App\View\Composers;

use App\Enums\QualificationStatus;
use App\Enums\TrainingStatus;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\Training;
use App\Models\personal\TrainingParticipant;
use Illuminate\View\View;

class QualifikationenComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (!$user) {
            $view->with([
                'eigeneAblaufendeQualifikationen' => collect(),
                'eigeneKommendeTrainings'         => collect(),
            ]);
            return;
        }

        $employeId = $user->employe_data?->id;

        // Eigene ablaufende oder abgelaufene Qualifikationen
        $ablaufend = $employeId
            ? EmployeeQualification::where('employe_id', $employeId)
                ->whereIn('status', [
                    QualificationStatus::Ablaufend->value,
                    QualificationStatus::Abgelaufen->value,
                ])
                ->with('qualificationType')
                ->orderBy('expiry_date')
                ->get()
            : collect();

        // Eigene kommende Fortbildungen (angemeldet, nicht abgesagt, in nächsten 60 Tagen)
        $kommendeTrainings = $employeId
            ? Training::whereHas('participants', function ($q) use ($employeId) {
                $q->where('employe_id', $employeId);
              })
              ->where('status', '!=', TrainingStatus::Abgesagt->value)
              ->where('start_date', '>=', now())
              ->where('start_date', '<=', now()->addDays(60))
              ->orderBy('start_date')
              ->limit(5)
              ->get()
            : collect();

        $view->with([
            'eigeneAblaufendeQualifikationen' => $ablaufend,
            'eigeneKommendeTrainings'         => $kommendeTrainings,
        ]);
    }
}

