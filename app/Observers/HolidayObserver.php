<?php

namespace App\Observers;

use App\Models\personal\Holiday;
use App\Services\Personal\Zeit\HolidayService;
use Illuminate\Support\Facades\Cache;

/**
 * Gleicht nach jeder relevanten Änderung eines Urlaubs Abwesenheit (Vertretungsplan),
 * Arbeitszeitnachweis und Dienstplan ab – unabhängig davon, woher die Änderung kommt
 * (Service, Import, Factory). Abwesenheiten sind über absences.holiday_id fest verknüpft.
 */
class HolidayObserver
{
    private const RELEVANT = ['approved', 'rejected', 'start_date', 'end_date', 'half_day', 'employe_id'];

    public function saved(Holiday $holiday): void
    {
        Cache::forget('user_holidays_'.$holiday->employe_id);

        if ($holiday->wasRecentlyCreated || $holiday->wasChanged(self::RELEVANT)) {
            app(HolidayService::class)->abgleichen($holiday);
        }
    }

    public function deleted(Holiday $holiday): void
    {
        Cache::forget('user_holidays_'.$holiday->employe_id);
        app(HolidayService::class)->abgleichen($holiday);
    }

    public function restored(Holiday $holiday): void
    {
        Cache::forget('user_holidays_'.$holiday->employe_id);
        app(HolidayService::class)->abgleichen($holiday);
    }
}
