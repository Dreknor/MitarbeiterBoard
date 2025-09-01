<?php

namespace App\Services;

use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\Absence;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AutoRosterPlanner
{
    /**
     * Erzeugt Vorschläge für die Umplanung bei abwesenden oder simulierten MA.
     * @param Roster $roster
     * @param int[] $simulateAbsentIds zusätzliche Mitarbeiter-IDs, die so behandelt werden als wären sie krank
     * @return array ['suggestions'=>[], 'summary'=>[]]
     */
    public function suggest(Roster $roster, array $simulateAbsentIds = []): array
    {
        $weekStart = $roster->start_date->copy();
        $weekEnd   = $weekStart->copy()->endOfWeek();

        $employes = $roster->department->activeEmployes($weekStart, $weekEnd);

        // Relevante Abwesenheiten in der Woche
        $absences = Absence::query()
            ->where('start','<=',$weekEnd)
            ->where('end','>=',$weekStart)
            ->get();

        // Abwesenheits-Mapping pro Tag
        $absentPerDay = [];
        for ($d=$weekStart->copy(); $d->lessThanOrEqualTo($weekEnd); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $absentPerDay[$key] = [];
            foreach ($absences as $a) {
                if ($d->between($a->start, $a->end)) {
                    $absentPerDay[$key][$a->users_id] = $a->reason;
                }
            }
            foreach ($simulateAbsentIds as $sid) { $absentPerDay[$key][$sid] = 'Simuliert'; }
        }

        $workingTimes = $roster->working_times()->get();
        $events       = $roster->events()->get();

        // Soll-Minuten pro Woche (percent * 40h)
        $targetMinutes = [];
        foreach ($employes as $e) {
            $percent = $e->employments()->where('department_id',$roster->department_id)->active()->get()->sum('percent');
            $targetMinutes[$e->id] = (int) round(($percent * 40 / 100) * 60);
        }

        // Geplante Minuten bisher
        $plannedMinutes = [];
        foreach ($employes as $e) { $plannedMinutes[$e->id] = 0; }
        foreach ($workingTimes as $wt) {
            if (isset($plannedMinutes[$wt->employe_id])) {
                $plannedMinutes[$wt->employe_id] += ($wt->duration ?? 0);
            }
        }

        $findWorkingTime = function(int $empId, string $dayKey) use ($workingTimes) {
            return $workingTimes->first(function($wt) use ($empId,$dayKey){
                return $wt->employe_id === $empId && $wt->date->format('Y-m-d') === $dayKey;
            });
        };
        $hasBreak = function(int $empId, string $dayKey) use ($events) {
            return $events->first(function($ev) use ($empId,$dayKey){
                return $ev->employe_id === $empId && $ev->date->format('Y-m-d') === $dayKey && Str::contains($ev->event,['pause','Pause']);
            }) !== null;
        };

        $suggestions = [];
        $totalAffected=0; $totalReassigned=0; $totalUnassign=0; $totalAdded=0; $totalNewBreaks=0;

        foreach ($events as $ev) {
            if (is_null($ev->employe_id)) { continue; }
            $dayKey = $ev->date->format('Y-m-d');
            if (!isset($absentPerDay[$dayKey][$ev->employe_id])) { continue; }
            $totalAffected++;

            $fromUser = $employes->firstWhere('id',$ev->employe_id);
            $startStr = $ev->start->format('H:i');
            $endStr   = $ev->end->format('H:i');

            // Kandidaten
            $candidates=[];
            foreach ($employes as $cand) {
                if ($cand->id === $ev->employe_id) continue;
                if (isset($absentPerDay[$dayKey][$cand->id])) continue;
                $wt = $findWorkingTime($cand->id,$dayKey);
                if (!$wt) continue;
                $covers = ($wt->start && $wt->end && $wt->start->format('H:i') <= $startStr && $wt->end->format('H:i') >= $endStr);
                $needAdjust=false; $added=0; $newStart=null; $newEnd=null;
                if (!$covers) {
                    if ($wt->start && $wt->start->format('H:i') > $startStr) {
                        $newStart = $startStr;
                        $added += $wt->start->diffInMinutes(Carbon::createFromFormat('Y-m-d H:i',$wt->date->format('Y-m-d').' '.$startStr));
                    }
                    if ($wt->end && $wt->end->format('H:i') < $endStr) {
                        $newEnd = $endStr;
                        $added += Carbon::createFromFormat('Y-m-d H:i',$wt->date->format('Y-m-d').' '.$endStr)->diffInMinutes($wt->end);
                    }
                    if ($added>0) $needAdjust=true;
                }
                $planned = $plannedMinutes[$cand->id];
                $target  = $targetMinutes[$cand->id] ?? 0;
                $overAfter = ($planned + ($needAdjust?$added:0)) - $target;
                $candidates[] = [
                    'user'=>$cand,
                    'wt'=>$wt,
                    'covers'=>$covers,
                    'needAdjust'=>$needAdjust,
                    'added'=>$added,
                    'underBefore'=>$target - $planned,
                    'overAfter'=>$overAfter,
                    'newStart'=>$newStart,
                    'newEnd'=>$newEnd,
                ];
            }

            if (empty($candidates)) {
                $suggestions[] = [
                    'event_id'=>$ev->id,
                    'event_name'=>$ev->event,
                    'date'=>$dayKey,
                    'from'=>['id'=>$fromUser?->id,'name'=>$fromUser?->vorname],
                    'to'=>null,
                    'action'=>'unassign',
                    'reason'=>'Keine verfügbaren Mitarbeitenden',
                    'adjust_working_time'=>null,
                    'add_break'=>null,
                ];
                $totalUnassign++;
                continue;
            }

            $best = collect($candidates)->sortBy([
                ['covers','desc'],
                ['needAdjust','asc'],
                ['underBefore','desc'],
                ['overAfter','asc'],
                ['added','asc'],
            ])->first();

            $adjust=null; $breakSuggestion=null;
            if ($best['needAdjust']) {
                $adjust = [
                    'working_time_id'=>$best['wt']->id,
                    'new_start'=>$best['newStart'],
                    'new_end'=>$best['newEnd'],
                    'added_minutes'=>$best['added']
                ];
                $totalAdded += $best['added'];
            }
            $wtStart = $best['newStart'] ?? $best['wt']->start->format('H:i');
            $wtEnd   = $best['newEnd']   ?? $best['wt']->end->format('H:i');
            $dur = Carbon::createFromFormat('H:i',$wtStart)->diffInMinutes(Carbon::createFromFormat('H:i',$wtEnd));
            if ($dur>360 && !$hasBreak($best['user']->id,$dayKey)) {
                $mid = Carbon::createFromFormat('H:i',$wtStart)->addMinutes((int)($dur/2)-15);
                $breakSuggestion = [
                    'employe_id'=>$best['user']->id,
                    'date'=>$dayKey,
                    'start'=>$mid->format('H:i'),
                    'end'=>$mid->copy()->addMinutes(30)->format('H:i'),
                    'event'=>'Pause'
                ];
                $totalNewBreaks++;
            }

            $suggestions[] = [
                'event_id'=>$ev->id,
                'event_name'=>$ev->event,
                'date'=>$dayKey,
                'from'=>['id'=>$fromUser?->id,'name'=>$fromUser?->vorname],
                'to'=>['id'=>$best['user']->id,'name'=>$best['user']->vorname],
                'action'=>'reassign',
                'reason'=>$best['covers']? 'Direkte Übernahme':'Übernahme mit Anpassung',
                'adjust_working_time'=>$adjust,
                'add_break'=>$breakSuggestion,
            ];
            $totalReassigned++;
        }

        // Indices & Summary
        foreach ($suggestions as $i=>&$s){ $s['index']=$i; }
        $summary = [
            'betroffene_events'=>$totalAffected,
            'neu_zugewiesen'=>$totalReassigned,
            'nicht_zuweisbar'=>$totalUnassign,
            'zusatz_minuten'=>$totalAdded,
            'neue_pausen'=>$totalNewBreaks,
        ];
        return ['suggestions'=>$suggestions,'summary'=>$summary];
    }
}
