<?php

namespace App\View\Composers;

use App\Models\personal\Roster;
use App\Models\personal\WorkingTime;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dashboard-Kachel "Dienstplan": alle veröffentlichten Pläne ab der aktuellen Woche,
 * in denen die Person steht (eigene Abteilung oder eingeplant mit Dienst/Termin) –
 * deckungsgleich mit RosterPolicy::view. Planende sehen zusätzlich die Entwürfe ihrer Abteilungen.
 */
class RosterComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        $abteilungen = $user->groups_rel->pluck('id');
        $wochenbeginn = Carbon::now()->startOfWeek();
        $eingeplant = fn ($q) => $q->where('employe_id', $user->id);

        $rosters = Roster::query()
            ->where('type', '!=', 'template')
            ->whereDate('start_date', '>=', $wochenbeginn->toDateString())
            ->where(function ($q) use ($user, $abteilungen, $eingeplant) {
                $q->where(fn ($q) => $q->where('published', true)
                    ->where(fn ($q) => $q->whereIn('department_id', $abteilungen)
                        ->orWhereHas('working_times', $eingeplant)
                        ->orWhereHas('events', $eingeplant)));

                if ($user->can('create roster')) {
                    $q->orWhereIn('department_id', $abteilungen);
                }
            })
            ->withExists([
                'working_times as mit_dienst' => $eingeplant,
                'events as mit_termin' => $eingeplant,
            ])
            ->with('department')
            ->orderBy('start_date')
            ->get()
            ->sortBy(fn (Roster $r) => [$r->start_date->timestamp, $r->department?->name])
            ->values();

        // Wer arbeitet heute? – aus allen veröffentlichten Plänen der aktuellen Woche
        $aktuell = $rosters->filter(fn (Roster $r) => $r->published && $r->start_date->isSameDay($wochenbeginn));
        $heute = WorkingTime::query()
            ->whereIn('roster_id', $aktuell->pluck('id'))
            ->whereDate('date', Carbon::today()->toDateString())
            ->where(fn ($q) => $q->whereNotNull('start')->orWhereNotNull('end'))
            ->orderBy('start')
            ->get()
            ->groupBy('roster_id');

        $view->with('rosters', $rosters);
        $view->with('wochen', $rosters->groupBy(fn (Roster $r) => $r->start_date->toDateString()));
        $view->with('heute', $aktuell->filter(fn (Roster $r) => $heute->has($r->id))
            ->map(fn (Roster $r) => ['roster' => $r, 'zeiten' => $heute[$r->id]])
            ->values());
    }
}
