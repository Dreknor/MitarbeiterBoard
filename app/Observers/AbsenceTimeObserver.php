<?php

namespace App\Observers;

use App\Models\Absence;
use App\Services\Personal\Zeit\RosterService;
use App\Services\Personal\Zeit\TimesheetService;
use Carbon\Carbon;

/**
 * Abwesenheiten (krank, Fortbildung …) wirken auf Arbeitszeitnachweis und Dienstplan.
 * Urlaubs-Abwesenheiten (holiday_id gesetzt) gleicht bereits der HolidayObserver ab.
 */
class AbsenceTimeObserver
{
    public function saved(Absence $absence): void
    {
        if ($absence->holiday_id !== null) {
            return;
        }

        if (!$absence->wasRecentlyCreated && !$absence->wasChanged(['start', 'end', 'reason', 'users_id'])) {
            return;
        }

        $von = $absence->start;
        $bis = $absence->end;
        if ($absence->wasChanged(['start', 'end']) && !$absence->wasRecentlyCreated) {
            $von = Carbon::parse($absence->getOriginal('start'))->min($absence->start);
            $bis = Carbon::parse($absence->getOriginal('end'))->max($absence->end);
        }

        $this->abgleichen($absence, $von, $bis);
    }

    public function deleted(Absence $absence): void
    {
        if ($absence->holiday_id === null) {
            $this->abgleichen($absence, $absence->start, $absence->end);
        }
    }

    private function abgleichen(Absence $absence, $von, $bis): void
    {
        $user = $absence->user;
        if ($user === null || !$user->exists) {
            return;
        }

        app(TimesheetService::class)->syncAbwesenheiten($user, Carbon::parse($von), Carbon::parse($bis));
        app(RosterService::class)->abwesenheitenAbgleichen($user, Carbon::parse($von), Carbon::parse($bis));
    }
}
