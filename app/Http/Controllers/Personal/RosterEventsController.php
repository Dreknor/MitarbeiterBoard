<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateTaskRequest;
use App\Http\Requests\personal\EditRosterEventRequest;
use App\Http\Requests\personal\TrashRosterDayRequest;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RosterEventsController extends Controller
{


    /**
     * Store a newly created resource in storage.
     *
     * @param CreateTaskRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateTaskRequest $request, Roster $roster)
    {

        $events = $roster->events;
        $employes = $roster->department->employes;


        foreach ($request->employes as $employe) {

            $task = new RosterEvents($request->validated());
            $task->roster_id = $roster->id;
            $task->employe_id = $employe;

            if (!$events->searchRosterEvent($employes->where('id', $employe)->first(), Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->start))->count() > 0 and !$events->searchRosterEvent($employes->where('id', $employe)->first(), Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end)->subMinute())->count() > 0) {
                $task->save();

            }
        }
        return redirectBack('success', 'Termin gespeichert', '#' . $task->date->format('Y-m-d'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param RosterEvents $rosterEvent
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(EditRosterEventRequest $request, RosterEvents $rosterEvent)
    {

        if (count($request->employes) == 1) {
            $attributes = $request->validated();
            $attributes['employe_id'] = $request->employes[0];

            if (Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end)->lessThan(Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end))) {
                $attributes['start'] = $request->end;
                $attributes['end'] = $request->start;

            }
            $rosterEvent->update($attributes);

        } else {
            $events = $rosterEvent->roster->events;
            $employes = $rosterEvent->roster->department->employes;

            foreach ($request->employes as $key => $employe) {
                if ($key === array_key_first($request->employes)) {
                    $attributes = $request->validated();
                    $attributes['employe_id'] = $employe;
                    if (Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end)->lessThan(Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end))) {
                        $attributes['start'] = $request->end;
                        $attributes['end'] = $request->start;

                    }
                    $rosterEvent->update($attributes);
                } else {
                    if (!$events->searchRosterEvent($employes->where('id', $employe)->first(), Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->start))->count() > 0 and !$events->searchRosterEvent($employes->where('id', $employe)->first(), Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end))->count() > 0) {
                        $task = new RosterEvents($request->validated());
                        $task->roster_id = $rosterEvent->roster_id;
                        $task->employe_id = $employe;


                        if (Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end)->lessThan(Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end))) {
                            $attributes['start'] = $request->end;
                            $attributes['end'] = $request->start;

                        }

                        $task->save();
                    } elseif (optional($events->searchRosterEvent($employes->where('id', $employe)->first(), Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->start))->first())->id == $rosterEvent->id) {
                        $attributes = $request->validated();
                        $attributes['employe_id'] = $employe;
                        if (Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end)->lessThan(Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end))) {
                            $attributes['start'] = $request->end;
                            $attributes['end'] = $request->start;

                        }
                        $rosterEvent->update($attributes);
                    }
                }


            }


        }

        return redirectBack(null, null, '#' . $rosterEvent->date->format('Y-m-d'));


    }

    public function dropUpdate(Request $request)
    {
        if (!auth()->check() || !auth()->user()->can('create roster')) {
            return response(['error' => 'forbidden'], 403);
        }
        $request->validate([
            'task' => 'required|string',
            'employe_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'start' => 'required|date_format:H:i',
            'end' => 'nullable|date_format:H:i'
        ]);

        $task = RosterEvents::where('id', \Illuminate\Support\Str::after($request->task, 'task_'))->first();
        if(!$task){ return response(['error'=>'not_found'],404); }

        $events = $task->roster->events; // bereits geladen (Collection)
        $employes = $task->roster->department->employes;

        $newStart = Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->start);
        if($newStart->format('H:i') < '08:00') { $newStart = Carbon::createFromFormat('Y-m-d H:i', $request->date.' 08:00'); }
        if($newStart->format('H:i') > '14:30') { $newStart = Carbon::createFromFormat('Y-m-d H:i', $request->date.' 14:15'); }

        if($request->filled('end')) {
            $newEnd = Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->end);
            if($newEnd->lessThanOrEqualTo($newStart)) { $newEnd = $newStart->copy()->addMinutes(15); }
        } else {
            $newEnd = $newStart->copy()->addMinutes($task->duration);
        }
        if($newEnd->format('H:i') > '14:30') {
            $newEnd = Carbon::createFromFormat('Y-m-d H:i', $request->date.' 14:30');
        }

        $employe = $employes->where('id', $request->employe_id)->first();
        $conflictStart = $events->searchRosterEvent($employe, $newStart->copy()->addMinute())->where('id','!=',$task->id)->count() > 0;
        $conflictEnd = $events->searchRosterEvent($employe, $newEnd->copy()->subMinute())->where('id','!=',$task->id)->count() > 0;
        $conflict = $conflictStart || $conflictEnd;

        if(!$conflict){
            $task->update([
                'employe_id' => $request->employe_id,
                'date' => $request->date,
                'start' => $newStart,
                'end' => $newEnd,
            ]);
        }

        $fresh = $task->fresh();
        return response([
            'id' => $fresh->id,
            'employe_id' => $fresh->employe_id,
            'date' => $fresh->date->format('Y-m-d'),
            'event' => $fresh->event,
            'start' => $fresh->start->format('H:i'),
            'end' => $fresh->end->format('H:i'),
            'conflict' => $conflict
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param RosterEvents $rosterEvent
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(RosterEvents $rosterEvent)
    {
        $day = $rosterEvent->date->format('Y-m-d');
        $rosterEvent->delete();

        return redirectBack('warning', 'Aufgabe wurde gelöscht', '#' . $day);
    }

    public function trashDay(Roster $roster, TrashRosterDayRequest $request)
    {
        if ($roster->id == $request->roster_id) {
            $roster->events()->whereDate('date', $request->date)->delete();
            $roster->working_times()->whereDate('date', $request->date)->delete();
            Cache::forget('roster_'.$roster->id.'_'.Carbon::createFromFormat('Y-m-d',$request->date)->format('Ymd'));

            return redirectBack('success', 'Alle Termine wurden gelöscht.', '#' . $request->date);
        }

        return redirectBack('warning', 'Termine konnten nicht gelöscht werden.', '#' . $request->date);
    }

    public function remember(RosterEvents $event)
    {
        if (auth()->user()->can('create roster')) {
            Cache::forget('roster_'.$event->roster_id.'_'.$event->date->format('Ymd'));
            $event->update([
                'employe_id' => null
            ]);


            return redirectBack('success', 'Termin gemerkt', '#'.$event->date->format('Y-m-d'));
        }

        return redirectBack('warning', 'Berechtigung.');
    }

    /**
     * Vorschau: Zeigt Kalender-Termine der Roster-Woche zur Auswahl an.
     */
    public function importFromCalendarPreview(Request $request, Roster $roster)
    {
        $user      = auth()->user();
        $kalender  = $this->sichtbareKalender($user);
        $startDate = $roster->start_date->copy()->startOfDay();
        $endDate   = $startDate->copy()->addDays(6)->endOfDay();

        $selectedKalenderId = $request->filled('kalender_id')
            ? (int) $request->kalender_id
            : $kalender->first()?->id;

        $termine = collect();
        if ($selectedKalenderId && $kalender->pluck('id')->contains($selectedKalenderId)) {
            $termine = OxTermin::where('ox_calendar_id', $selectedKalenderId)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('beginn', [$startDate, $endDate])
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          // Mehrtägige Termine die in die Woche hineinragen
                          $q2->where('beginn', '<=', $endDate)
                             ->where('ende', '>=', $startDate);
                      });
                })
                ->whereNull('rrule') // Wiederholungstermine zunächst ausschließen
                ->orderBy('beginn')
                ->get();
        }

        // Bereits importierte ox_termin_ids für diesen Roster ermitteln
        $bereitsImportiert = $roster->events()
            ->whereNotNull('ox_termin_id')
            ->pluck('ox_termin_id')
            ->toArray();

        return view('personal.rosters.import_calendar', compact(
            'roster', 'kalender', 'termine', 'selectedKalenderId',
            'startDate', 'endDate', 'bereitsImportiert'
        ));
    }

    /**
     * Import: Legt ausgewählte Kalender-Termine als nichtzugewiesene Dienstplan-Ereignisse an.
     */
    public function importFromCalendar(Request $request, Roster $roster)
    {
        $request->validate([
            'ox_termin_ids'   => 'required|array|min:1',
            'ox_termin_ids.*' => 'required|integer|exists:ox_termine,id',
        ]);

        $user      = auth()->user();
        $kalender  = $this->sichtbareKalender($user);

        $bereitsImportiert = $roster->events()
            ->whereNotNull('ox_termin_id')
            ->pluck('ox_termin_id')
            ->toArray();

        $importiert = 0;
        $uebersprungen = 0;

        foreach ($request->ox_termin_ids as $terminId) {
            // Duplikat-Schutz
            if (in_array($terminId, $bereitsImportiert)) {
                $uebersprungen++;
                continue;
            }

            $termin = OxTermin::find($terminId);
            if (!$termin) {
                continue;
            }

            // Sichtbarkeits-Check
            if (!$kalender->pluck('id')->contains($termin->ox_calendar_id)) {
                continue;
            }

            if ($termin->ganztaegig) {
                $start = '08:00:00';
                $end   = '14:30:00';
            } else {
                $start = $termin->beginn->format('H:i:s');
                $end   = $termin->ende->format('H:i:s');
                // Zeiten auf Dienstplan-Grenzen kappen (08:00–14:30)
                if ($start < '08:00:00') { $start = '08:00:00'; }
                if ($end > '14:30:00')   { $end   = '14:30:00'; }
                if ($end <= $start)      { $end   = (new \DateTime($start))->modify('+15 minutes')->format('H:i:s'); }
            }

            $event = new RosterEvents([
                'roster_id'    => $roster->id,
                'employe_id'   => null,
                'date'         => $termin->beginn->toDateString(),
                'start'        => $start,
                'end'          => $end,
                'event'        => $termin->titel,
                'ox_termin_id' => $termin->id,
            ]);
            $event->save();

            Cache::forget('roster_'.$roster->id.'_'.$termin->beginn->format('Ymd'));
            $importiert++;
        }

        $msg = $importiert . ' Termin(e) importiert';
        if ($uebersprungen > 0) {
            $msg .= ', ' . $uebersprungen . ' bereits vorhanden übersprungen';
        }

        return redirect()->route('roster.show', $roster->id)
            ->with('Meldung', $msg)
            ->with('type', 'success');
    }

    /**
     * Hilfsmethode: Für den aktuellen User sichtbare Kalender laden.
     * (Analog zu CalendarController::sichtbareKalender)
     */
    protected function sichtbareKalender($user): Collection
    {
        return OxCalendar::where('sichtbar', true)
            ->with('groups')
            ->get()
            ->filter(function (OxCalendar $calendar) use ($user) {
                if ($user->can('manage calendar')) {
                    return true;
                }
                if ($calendar->groups->isEmpty()) {
                    return $user->can('view calendar');
                }
                $calendarGroupIds = $calendar->groups->pluck('id');
                $userGroupIds     = $user->groups()->pluck('id');
                return $calendarGroupIds->intersect($userGroupIds)->isNotEmpty();
            });
    }
}
