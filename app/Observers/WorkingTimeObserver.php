<?php

namespace App\Observers;


use App\Models\personal\WorkingTime;
use Illuminate\Support\Facades\Cache;

class WorkingTimeObserver
{
    /**
     * Handle the WorkingTime "created" event.
     *
     * @param  \App\Models\personal\WorkingTime   $workingTime
     * @return void
     */
    public function created(WorkingTime $workingTime)
    {
        Cache::forget('roster_'.$workingTime->roster_id.'_'.$workingTime->date->format('Ymd'));

    }

    /**
     * Handle the WorkingTime "updated" event.
     *
     * @param  \App\Models\personal\WorkingTime   $workingTime
     * @return void
     */
    public function updated(WorkingTime $workingTime)
    {
        Cache::forget('roster_'.$workingTime->roster_id.'_'.$workingTime->date->format('Ymd'));

    }

    /**
     * Handle the WorkingTime "deleted" event.
     *
     * @param  \App\Models\personal\WorkingTime   $workingTime
     * @return void
     */
    public function deleted(WorkingTime $workingTime)
    {
        Cache::forget('roster_'.$workingTime->roster_id.'_'.$workingTime->date->format('Ymd'));

    }

    /**
     * Handle the WorkingTime "restored" event.
     *
     * @param  \App\Models\personal\WorkingTime   $workingTime
     * @return void
     */
    public function restored(WorkingTime $workingTime)
    {
        Cache::forget('roster_'.$workingTime->roster_id.'_'.$workingTime->date->format('Ymd'));

    }

    /**
     * Handle the WorkingTime "force deleted" event.
     *
     * @param  \App\Models\personal\WorkingTime  $workingTime
     * @return void
     */
    public function forceDeleted(WorkingTime $workingTime)
    {
        Cache::forget('roster_'.$workingTime->roster_id.'_'.$workingTime->date->format('Ymd'));

    }
}
