<?php

namespace App\Observers;

use App\Models\Absence;
use App\Models\personal\Holiday;
use Carbon\Carbon;

class HolidayObserver
{
    /**
     * Handle the personalHoliday "created" event.
     */
    public function created(Holiday $holiday): void
    {

        if (settings('absence_auto_create', 'holidays') == true){
            if ($holiday->approved){
                Absence::firstOrCreate([
                    'users_id' => $holiday->employe_id,
                    'creator_id' => auth()->id(),
                    'reason' => 'Urlaub',
                    'start' => $holiday->start_date,
                    'end' => $holiday->end_date,
                ]);
            }
        }
    }

    /**
     * Handle the Holiday "updated" event.
     */
    public function updated(Holiday $holiday): void
    {

        if (settings('absence_auto_create', 'holidays') == true){
            if ($holiday->approved){
                Absence::firstOrCreate([
                    'users_id' => $holiday->employe_id,
                    'creator_id' => auth()->id(),
                    'reason' => 'Urlaub',
                    'start' => $holiday->start_date,
                    'end' => $holiday->end_date,
                ]);
            }
        }

        for ($x = $holiday->start_date; $x <= $holiday->end_date; $x->addDay()) {
            \Cache::forget('holiday_'.auth()->id().'_'.$x->format('Y-m-d'));
        }
    }

    /**
     * Handle the Holiday "deleted" event.
     */
    public function deleted(Holiday $holiday): void
    {
        if (settings('absence_auto_create', 'holidays') == true){
            if ($holiday->approved){
                Absence::where([
                    'users_id' => $holiday->employe_id,
                    'creator_id' => auth()->id(),
                    'reason' => 'Urlaub',
                    'start' => $holiday->start_date,
                    'end' => $holiday->end_date,
                ])->delete();
            }
        }
    }

    /**
     * Handle the personalHoliday "restored" event.
     */
    public function restored(Holiday $holiday): void
    {
        //
    }

    /**
     * Handle the personalHoliday "force deleted" event.
     */
    public function forceDeleted(Holiday $holiday): void
    {
        //
    }
}
