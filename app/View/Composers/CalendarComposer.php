<?php

namespace App\View\Composers;

use App\Models\OxSyncLog;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Illuminate\View\View;

class CalendarComposer
{
    public function __construct(
        protected OxCalendarService $calendarService
    ) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        if (!$user || !$user->can('view calendar')) {
            $view->with('naechsteTermine', collect());
            $view->with('syncStatus', null);
            return;
        }

        // Sichtbare Kalender-IDs über zentrale Service-Methode (kein duplizierter Code)
        $sichtbareKalenderIds = $this->calendarService
            ->sichtbareKalender($user)
            ->pluck('id');

        // Einfache (nicht-wiederkehrende) Termine
        $einfacheTermine = OxTermin::whereIn('ox_calendar_id', $sichtbareKalenderIds)
            ->where('beginn', '>=', now())
            ->whereNull('rrule')
            ->with('kalender')
            ->orderBy('beginn')
            ->limit(5)
            ->get();

        // Wiederkehrende Termine serverseitig expandieren (nächste 30 Tage) – TODO 25
        $rruleTermine = OxTermin::whereIn('ox_calendar_id', $sichtbareKalenderIds)
            ->whereNotNull('rrule')
            ->with('kalender')
            ->get();

        $expandierteTermine = collect();
        foreach ($rruleTermine as $termin) {
            $occurrences = $this->calendarService->expandRruleTermine(
                $termin,
                now(),
                now()->addDays(30)
            );
            foreach ($occurrences as $occ) {
                if ($occ['beginn']->gte(now())) {
                    $expandierteTermine->push((object) [
                        'id'          => $termin->id,
                        'titel'       => $termin->titel,
                        'beginn'      => $occ['beginn'],
                        'ende'        => $occ['ende'],
                        'ganztaegig'  => $termin->ganztaegig,
                        'ort'         => $termin->ort,
                        'beschreibung' => $termin->beschreibung,
                        'kalender'    => $termin->kalender,
                    ]);
                }
            }
        }

        // Zusammenführen, nach Beginn sortieren, Top 5
        $naechsteTermine = $einfacheTermine
            ->concat($expandierteTermine)
            ->sortBy('beginn')
            ->take(5)
            ->values();

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
                'fehler_24h'     => $aufeinanderfolgendeFehler,
                'letzter_fehler' => $letzterFehler,
                'zeige_warnung'  => $aufeinanderfolgendeFehler >= 3,
            ];
        }

        $view->with('naechsteTermine', $naechsteTermine);
        $view->with('syncStatus', $syncStatus);
    }
}
