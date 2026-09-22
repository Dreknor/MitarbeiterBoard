<?php

namespace App\View\Composers;

use App\Models\personal\TimesheetDays;
use Carbon\Carbon;
use Illuminate\View\View;

class TimeRecordingCardComposer
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
        $users = [];

        // Nur wirklich angemeldete Mitarbeiter: start gesetzt, end noch offen,
        // kein Prozent-Eintrag (Urlaub/Krankheit etc.).
        $timesheetDay = TimesheetDays::query()
            ->whereDate('date', Carbon::now()->format('Y-m-d'))
            ->whereNotNull('start')
            ->whereNull('end')
            ->whereNull('percent_of_workingtime')
            ->get();

        foreach ($timesheetDay as $day) {
            $users[$day->employe->name] = $day->start?->format('H:i');
        }

        $view->with('users', $users);
    }
}
