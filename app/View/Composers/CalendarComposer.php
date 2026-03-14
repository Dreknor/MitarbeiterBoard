<?php

namespace App\View\Composers;

use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use Illuminate\View\View;

class CalendarComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (!$user || !$user->can('view calendar')) {
            $view->with('naechsteTermine', collect());
            $view->with('syncStatus', null);
            return;
        }

        // Sichtbare Kalender-IDs ermitteln (gleiche Logik wie CalendarController)
        $sichtbareKalenderIds = OxCalendar::where('sichtbar', true)
            ->with('groups')
            ->get()
            ->filter(function (OxCalendar $calendar) use ($user) {
                if ($user->can('manage calendar')) {
                    return true;
                }
                if ($calendar->groups->isEmpty()) {
                    return true;
                }
                $calendarGroupIds = $calendar->groups->pluck('id');
                $userGroupIds = $user->groups_rel()->pluck('groups.id');
                return $calendarGroupIds->intersect($userGroupIds)->isNotEmpty();
            })
            ->pluck('id');

        // Nächste 5 anstehende Termine
        $naechsteTermine = OxTermin::whereIn('ox_calendar_id', $sichtbareKalenderIds)
            ->where('beginn', '>=', now())
            ->whereNull('rrule') // Einfache Termine (RRULE-Expansion wäre zu aufwändig für Dashboard)
            ->with('kalender')
            ->orderBy('beginn')
            ->limit(5)
            ->get();

        // Sync-Status für Admins
        $syncStatus = null;
        if ($user->can('manage calendar')) {
            $letzterFehler = OxSyncLog::where('aktion', 'error')
                ->latest()
                ->first();

            $aufeinanderfolgendeFehler = OxSyncLog::where('aktion', 'error')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $syncStatus = [
                'fehler_24h'      => $aufeinanderfolgendeFehler,
                'letzter_fehler'  => $letzterFehler,
                'zeige_warnung'   => $aufeinanderfolgendeFehler >= 3,
            ];
        }

        $view->with('naechsteTermine', $naechsteTermine);
        $view->with('syncStatus', $syncStatus);
    }
}

