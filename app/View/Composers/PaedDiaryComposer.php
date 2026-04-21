<?php

namespace App\View\Composers;

use App\Models\GradingDocumentationSession;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryTask;
use Carbon\Carbon;
use Illuminate\View\View;

/**
 * View Composer für die Dashboard-Card "Pädagogisches Tagebuch".
 *
 * Liefert:
 *   $paedKlassen          – Klassen des Users (id+name)
 *   $paedOffeneTasks      – Offene Aufgaben (limit 5)
 *   $paedOffeneEintraege  – Eigene Tagebuch-Einträge ohne Abschluss (limit 5)
 *   $paedOffeneDokus      – Offene Dokumentations-Sessions des Users (limit 5)
 *   $paedHeuteTermine     – Heutige Termine (inkl. Wiederholungen)
 *   $paedLetzterEintrag   – Zuletzt erstellter Tagebuch-Eintrag
 *   $paedOffeneCounts     – Anzahl offener Tasks pro Klasse
 */
class PaedDiaryComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        $defaults = [
            'paedKlassen'         => collect(),
            'paedOffeneTasks'     => collect(),
            'paedOffeneEintraege' => collect(),
            'paedOffeneDokus'     => collect(),
            'paedHeuteTermine'    => collect(),
            'paedLetzterEintrag'  => null,
            'paedOffeneCounts'    => collect(),
        ];

        if (!$user || !$user->can('view paed diary')) {
            $view->with($defaults);
            return;
        }

        // Klassen des Users
        try {
            $klassen = $user->paed_klassen()
                ->select('klassen.id', 'klassen.name')
                ->orderBy('klassen.name')
                ->get();
        } catch (\Throwable) {
            $klassen = collect();
        }

        $klassenIds = $klassen->pluck('id')->all();

        // Offene Aufgaben
        $offeneTasks = PaedDiaryTask::query()
            ->open()
            ->where(function ($q) use ($user, $klassenIds) {
                $q->where('created_by', $user->id);
                if (!empty($klassenIds)) {
                    $q->orWhereIn('klasse_id', $klassenIds);
                }
            })
            ->with(['klasse:id,name', 'schueler:id,vorname,nachname'])
            ->orderByRaw('highlighted DESC, due_date IS NULL, due_date ASC')
            ->limit(5)
            ->get();

        // Offene (nicht abgeschlossene) Tagebuch-Einträge des Users
        $offeneEintraege = PaedDiaryEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->with(['klasse:id,name', 'category:id,name,color'])
            ->orderByDesc('datum')
            ->limit(5)
            ->get();

        // Offene Dokumentations-Sessions des Users
        $offeneDokus = GradingDocumentationSession::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->with(['klasse:id,name', 'gradingSystem:id,name', 'schueler:id,vorname,nachname', 'group:id,name'])
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        // Heutige Termine
        $heute    = Carbon::today();
        $heuteEnd = Carbon::today()->endOfDay();
        $termine  = collect();

        try {
            $appointments = PaedDiaryAppointment::forUser($user->id)
                ->active()
                ->where(function ($q) use ($heute) {
                    $q->where(function ($q2) use ($heute) {
                        $q2->where('is_recurring', false)
                           ->whereDate('start_date', $heute);
                    })->orWhere(function ($q2) use ($heute) {
                        $q2->where('is_recurring', true)
                           ->whereDate('start_date', '<=', $heute)
                           ->where(function ($q3) use ($heute) {
                               $q3->whereNull('recurring_end_date')
                                  ->orWhereDate('recurring_end_date', '>=', $heute);
                           });
                    });
                })
                ->get();

            foreach ($appointments as $appt) {
                foreach ($appt->getOccurrencesInRange($heute, $heuteEnd) as $occ) {
                    $termine->push($occ);
                }
            }
            $termine = $termine->sortBy('start_time')->values();
        } catch (\Throwable) {
            $termine = collect();
        }

        // Zuletzt erstellter Eintrag
        try {
            $letzter = PaedDiaryEntry::query()
                ->where('user_id', $user->id)
                ->with('klasse:id,name')
                ->orderByDesc('datum')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable) {
            $letzter = null;
        }

        // Anzahl offener Tasks pro Klasse
        $offeneCountsProKlasse = collect();
        if (!empty($klassenIds)) {
            $offeneCountsProKlasse = PaedDiaryTask::query()
                ->open()
                ->whereIn('klasse_id', $klassenIds)
                ->selectRaw('klasse_id, COUNT(*) as cnt')
                ->groupBy('klasse_id')
                ->pluck('cnt', 'klasse_id');
        }

        $view->with([
            'paedKlassen'         => $klassen,
            'paedOffeneTasks'     => $offeneTasks,
            'paedOffeneEintraege' => $offeneEintraege,
            'paedOffeneDokus'     => $offeneDokus,
            'paedHeuteTermine'    => $termine,
            'paedLetzterEintrag'  => $letzter,
            'paedOffeneCounts'    => $offeneCountsProKlasse,
        ]);
    }
}

