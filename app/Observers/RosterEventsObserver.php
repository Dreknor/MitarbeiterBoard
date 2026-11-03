<?php

namespace App\Observers;

use App\Models\personal\RosterEvents;
use App\Services\Personal\Zeit\RosterService;
use Illuminate\Support\Facades\Cache;

/**
 * Termine im Dienstplan: Tages-Cache leeren und Änderungen an veröffentlichten
 * Plänen je betroffener Person protokollieren. Automatische Abwesenheits-
 * Markierungen werden nicht protokolliert.
 */
class RosterEventsObserver
{
    public function created(RosterEvents $rosterEvents)
    {
        $this->forget($rosterEvents);
        $this->log($rosterEvents->employe_id, $rosterEvents, 'Neuer Termin: '.$this->text($rosterEvents));
    }

    public function updated(RosterEvents $rosterEvents)
    {
        $this->forget($rosterEvents);

        if (!$rosterEvents->wasChanged(['employe_id', 'date', 'start', 'end', 'event'])) {
            return;
        }

        $vorher = $rosterEvents->getOriginal('employe_id');
        if ($rosterEvents->wasChanged('employe_id')) {
            $this->log($vorher, $rosterEvents, 'Termin abgegeben: '.$this->text($rosterEvents));
            $this->log($rosterEvents->employe_id, $rosterEvents, 'Termin übernommen: '.$this->text($rosterEvents));
            return;
        }

        $this->log($rosterEvents->employe_id, $rosterEvents, 'Termin geändert: '.$this->text($rosterEvents));
    }

    public function deleted(RosterEvents $rosterEvents)
    {
        $this->forget($rosterEvents);
        $this->log($rosterEvents->employe_id, $rosterEvents, 'Termin entfällt: '.$this->text($rosterEvents));
    }

    public function restored(RosterEvents $rosterEvents)
    {
        $this->forget($rosterEvents);
    }

    public function forceDeleted(RosterEvents $rosterEvents)
    {
        $this->forget($rosterEvents);
    }

    private function forget(RosterEvents $rosterEvents): void
    {
        Cache::forget('roster_'.$rosterEvents->roster_id.'_'.$rosterEvents->date->format('Ymd'));
    }

    private function text(RosterEvents $event): string
    {
        return $event->event.($event->start && $event->end ? ' '.$event->start->format('H:i').'–'.$event->end->format('H:i') : '');
    }

    private function log(?int $employeId, RosterEvents $event, string $text): void
    {
        if ($employeId === null || $event->is_abwesenheit) {
            return;
        }

        app(RosterService::class)->protokollieren($event->roster_id, $employeId, $event->date, $text);
    }
}
