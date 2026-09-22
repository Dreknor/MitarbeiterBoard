<?php

namespace App\View\Composers;

use App\Models\personal\EmployeData;
use Illuminate\View\View;

class GeburtstageComposer
{
    public function compose(View $view): void
    {
        $today   = now();
        $endDate = now()->addDays(14);

        $geburtstage = EmployeData::whereNotNull('geburtstag')
            ->whereHas('user', fn($q) => $q->whereNull('deleted_at'))
            ->get()
            ->filter(function ($emp) use ($today, $endDate) {
                $birthday = $emp->geburtstag->copy()->year($today->year);
                if ($birthday->lt($today->copy()->startOfDay())) {
                    $birthday->addYear();
                }
                return $birthday->between($today->copy()->startOfDay(), $endDate->copy()->endOfDay());
            })
            ->sortBy(function ($emp) use ($today) {
                $birthday = $emp->geburtstag->copy()->year($today->year);
                if ($birthday->lt($today->copy()->startOfDay())) {
                    $birthday->addYear();
                }
                return $birthday->timestamp;
            })
            ->take(10);

        $view->with('geburtstage', $geburtstage);
    }
}

