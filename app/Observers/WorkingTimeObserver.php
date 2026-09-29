<?php

namespace App\Observers;

use App\Models\personal\WorkingTime;
use App\Services\Personal\Zeit\RosterService;
use Illuminate\Support\Facades\Cache;

/**
 * Arbeitszeiten im Dienstplan: Tages-Cache leeren und Änderungen an
 * veröffentlichten Plänen protokollieren (Grundlage der Änderungsmitteilung).
 */
class WorkingTimeObserver
{
    public function created(WorkingTime $workingTime)
    {
        $this->forget($workingTime);
        $this->log($workingTime, 'Dienst eingetragen: '.$this->zeit($workingTime));
    }

    public function updated(WorkingTime $workingTime)
    {
        $this->forget($workingTime);

        if ($workingTime->wasChanged(['start', 'end', 'function', 'date'])) {
            $this->log($workingTime, 'Dienst geändert: '.$this->zeit($workingTime));
        }
    }

    public function deleted(WorkingTime $workingTime)
    {
        $this->forget($workingTime);
        $this->log($workingTime, 'Dienst entfernt');
    }

    public function restored(WorkingTime $workingTime)
    {
        $this->forget($workingTime);
    }

    public function forceDeleted(WorkingTime $workingTime)
    {
        $this->forget($workingTime);
    }

    private function forget(WorkingTime $workingTime): void
    {
        Cache::forget('roster_'.$workingTime->roster_id.'_'.$workingTime->date->format('Ymd'));
    }

    private function zeit(WorkingTime $workingTime): string
    {
        if ($workingTime->start === null || $workingTime->end === null) {
            return 'frei';
        }

        return $workingTime->start->format('H:i').'–'.$workingTime->end->format('H:i').' Uhr'
            .($workingTime->function ? ' ('.$workingTime->function.')' : '');
    }

    private function log(WorkingTime $workingTime, string $text): void
    {
        app(RosterService::class)->protokollieren($workingTime->roster_id, $workingTime->employe_id, $workingTime->date, $text);
    }
}
