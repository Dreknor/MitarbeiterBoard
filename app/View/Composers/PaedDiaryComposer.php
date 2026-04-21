<?php

namespace App\View\Composers;

use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryTask;
use Carbon\Carbon;
use Illuminate\View\View;

/**
 * View Composer für die Dashboard-Card "Pädagogisches Tagebuch".
 *
 * Liefert:
 *   $paedKlassen      – Klassen des Users (nur id+name, mit Anzahl offener Aufgaben)
 *   $paedOffeneTasks  – Offene Aufgaben des Users (sortiert nach due_date)
 *   $paedHeuteTermine – Heutige Termine (inkl. Wiederholungen)
 *   $paedLetzterEintrag – Zuletzt erstellter Tagebuch-Eintrag
 */
class PaedDiaryComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        $defaults = [
            'paedKlassen'        => collect(),
            'paedOffeneTasks'    => collect(),
            'paedHeuteTermine'   => collect(),
            'paedLetzterEintrag' => null,
        ];

        if (!$user || !$user->can('view paed diary')) {
            $view->with($defaults);
            return;
        }

        // Klassen des Users (nur id+name)
        try {
            $klassen = $user->paed_klassen()
                ->select('klassen.id', 'klassen.name')
                ->orderBy('klassen.name')
                ->get();
        } catch (\Throwable) {
            $klassen = collect();
        }

        $klassenIds = $klassen->pluck('id')->all();

        // Offene Aufgaben: eigene + Aufgaben in Klassen des Users
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

        // Heutige Termine (inkl. Wiederholungen)
        $heute       = Carbon::today();
        $heuteEnd    = Carbon::today()->endOfDay();
        $termine     = collect();

        try {
            $appointments = PaedDiaryAppointment::forUser($user->id)
                ->active()
                ->where(function ($q) use ($heute) {
                    // Entweder einmaliger Termin heute ODER Wiederholung, die
                    // spätestens heute gestartet ist und (noch) nicht endet.
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

        // Zuletzt erstellter Eintrag (nur Metadaten – content ist verschlüsselt)
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

        // Anzahl offener Tasks pro Klasse (für Chip-Anzeige)
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
            'paedKlassen'           => $klassen,
            'paedOffeneTasks'       => $offeneTasks,
            'paedHeuteTermine'      => $termine,
            'paedLetzterEintrag'    => $letzter,
            'paedOffeneCounts'      => $offeneCountsProKlasse,
        ]);
    }
}


