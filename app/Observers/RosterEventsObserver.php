<?php

namespace App\Observers;


use App\Models\personal\RosterEvents;
use Illuminate\Support\Facades\Cache;

class RosterEventsObserver
{
    /**
     * Handle the RosterEvents "created" event.
     *
     * @param  \App\Models\personal\RosterEvents  $rosterEvents
     * @return void
     */
    public function created(RosterEvents $rosterEvents)
    {
        Cache::forget('roster_'.$rosterEvents->roster_id.'_'.$rosterEvents->date->format('Ymd'));
    }

    /**
     * Handle the RosterEvents "updated" event.
     *
     * @param  \App\Models\personal\RosterEvents  $rosterEvents
     * @return void
     */
    public function updated(RosterEvents $rosterEvents)
    {
        Cache::forget('roster_'.$rosterEvents->roster_id.'_'.$rosterEvents->date->format('Ymd'));
    }

    /**
     * Handle the RosterEvents "deleted" event.
     *
     * @param  \App\Models\personal\RosterEvents  $rosterEvents
     * @return void
     */
    public function deleted(RosterEvents $rosterEvents)
    {
        Cache::forget('roster_'.$rosterEvents->roster_id.'_'.$rosterEvents->date->format('Ymd'));
    }

    /**
     * Handle the RosterEvents "restored" event.
     *
     * @param  \App\Models\personal\RosterEvents  $rosterEvents
     * @return void
     */
    public function restored(RosterEvents $rosterEvents)
    {
        Cache::forget('roster_'.$rosterEvents->roster_id.'_'.$rosterEvents->date->format('Ymd'));
    }

    /**
     * Handle the RosterEvents "force deleted" event.
     *
     * @param  \App\Models\personal\RosterEvents  $rosterEvents
     * @return void
     */
    public function forceDeleted(RosterEvents $rosterEvents)
    {
        Cache::forget('roster_'.$rosterEvents->roster_id.'_'.$rosterEvents->date->format('Ymd'));
    }
}
