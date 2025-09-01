<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateTaskRequest;
use App\Http\Requests\personal\EditRosterEventRequest;
use App\Http\Requests\personal\TrashRosterDayRequest;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
}
