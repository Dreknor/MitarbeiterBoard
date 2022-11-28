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
use Illuminate\Support\Str;

class RosterEventsController extends Controller
{


    /**
     * Store a newly created resource in storage.
     *
     * @param CreateTaskRequest $request
     * @return Response
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
        return redirectBack(null, null, '#' . $task->date);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param RosterEvents $rosterEvent
     * @return Response
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

        return redirectBack(null, null, '#' . $rosterEvent->date);


    }

    public function dropUpdate(Request $request)
    {
        $task = RosterEvents::where('id', Str::after($request->task, 'task_'))->first();

        $events = $task->roster->events;
        $employes = $task->roster->department->employes;

        $duration = $task->duration;
        $newStart = Carbon::createFromFormat('Y-m-d H:i', $request->date . ' ' . $request->start);
        $newEnd = $newStart->copy()->addMinutes($duration);


        if (!$events->searchRosterEvent($employes->where('id', $request->employe_id)->first(), $newStart->copy()->addMinute())->count() > 0
            and !$events->searchRosterEvent($employes->where('id', $request->employe_id)->first(), $newEnd->copy()->subMinute())->count() > 0) {
            $task->update([
                'employe_id' => $request->employe_id,
                'date' => $request->date,
                'start' => $newStart,
                'end' => $newEnd,

            ]);
        }


        return \response($task);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param RosterEvents $rosterEvent
     * @return Response
     */
    public function destroy(RosterEvents $rosterEvent)
    {
        $day = $rosterEvent->date;
        $rosterEvent->delete();

        return redirectBack('warning', 'Aufgabe wurde gelöscht', '#' . $day);
    }

    public function trashDay(Roster $roster, TrashRosterDayRequest $request)
    {
        if ($roster->id == $request->roster_id) {
            $roster->events()->whereDate('date', $request->date)->delete();
            $roster->working_times()->whereDate('date', $request->date)->delete();
            return redirectBack('success', 'Alle Termine wurden gelöscht.', '#' . $request->date);
        }

        return redirectBack('warning', 'Termine konnten nicht gelöscht werden.');
    }

    public function remember(RosterEvents $event)
    {
        if (auth()->user()->can('create roster')) {
            $event->update([
                'employe_id' => null
            ]);
            return redirectBack();
        }

        return redirectBack('warning', 'Berechtigung.');
    }
}