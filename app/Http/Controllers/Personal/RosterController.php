<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createRosterRequest;
use App\Mail\SendRosterMail;
use App\Models\Absence;
use App\Models\Group;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\User;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
//use Barryvdh\DomPDF\Facade\Pdf AS PDF;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RosterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {

        return view('personal.rosters.index', [
            'departments' => Group::where('needsRoster', true)->with('rosters')->get()
        ]);
    }

    public function publish(Roster $roster)
    {
        if (!auth()->user()->can('create roster')) {
            return redirectBack('danger', 'Berechtigung fehlt');
        }


        $roster->update(
            [
                'published' => true
            ]
        );

        return redirectBack('success', 'Dienstplan veröffentlicht');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create(Group $department)
    {

        return view('personal.rosters.create', [
            'department' => $department,
            'templates' => $department->rosters()->where('type', 'template')->orderByDesc('start_date')->limit(5)->get()
        ]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(createRosterRequest $request)
    {
        $roster = new Roster($request->validated());
        $roster->save();
        $weekStart = $roster->start_date->copy();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $employes = $roster->department->activeEmployes($weekStart, $weekEnd);

        for ($day = $weekStart->copy(); $day->lessThanOrEqualTo($weekEnd); $day->addDay()) {
            if (is_holiday($day)) {
                foreach ($employes as $employe) {
                    $event = new RosterEvents([
                        'roster_id' => $roster->id,
                        'employe_id' => $employe->id,
                        'date' => $day->copy(),
                        'start' => '08:00:00',
                        'end' => '14:30:00',
                        'event' => is_holiday($day)['title'],
                    ]);
                    $event->save();
                }
            }
        }

        if ($request->used_template != null) {
            $template = Roster::findOrFail($request->used_template);
            $templateEvents = $template->events;
            $templateWorkingTimes = $template->working_times;
            $templateStart = $template->start_date->copy();
            $newRosterStart = $weekStart->copy();

            foreach ($templateEvents as $event) {
                $days = $templateStart->diffInDays($event->date);
                $targetDay = $newRosterStart->copy()->addDays($days);
                if (is_holiday($targetDay)) {
                    continue;
                }
                $newEvent = $event->replicate();
                $newEvent->roster_id = $roster->id;
                $newEvent->date = $targetDay;
                if (is_null($employes->firstWhere('id', $event->employe_id))) {
                    $newEvent->employe_id = null;
                }
                $newEvent->save();
            }

            foreach ($templateWorkingTimes as $workingTime) {
                if (!is_null($employes->firstWhere('id', $workingTime->employe_id))) {
                    $newWorkingTime = $workingTime->replicate();
                    $newWorkingTime->roster_id = $roster->id;
                    $newWorkingTime->googleCalendarId = null;
                    $days = $templateStart->diffInDays($workingTime->date);
                    $newWorkingTime->date = $newRosterStart->copy()->addDays($days);
                    $newWorkingTime->save();
                }
            }
        }

        foreach ($employes as $employe) {
            if ($roster->type == 'normal') {
                $employes_holidays = $employe->holidays()->where('start_date', '<=', $weekEnd)->where('end_date', '>=', $weekStart)->get();
                for ($x = $weekStart->copy(); $x <= $weekEnd; $x->addDay()) {
                    $holiday = $employes_holidays->where('start_date', '<=', $x)->where('end_date', '>=', $x)->first();
                    if ($holiday) {
                        $roster->events()->where('employe_id', $employe->id)->where('date', $x)->update(['employe_id' => null]);
                        $roster->working_times()->where('employe_id', $employe->id)->where('date', $x)->delete();
                        $event = new RosterEvents([
                            'roster_id' => $roster->id,
                            'employe_id' => $employe->id,
                            'date' => $x->copy(),
                            'start' => '08:00:00',
                            'end' => '14:30:00',
                            'event' => 'Urlaub',
                        ]);
                        $event->save();
                    }
                }
                $employes_absences = Absence::where('users_id', $employe->id)
                    ->where('start', '<=', $weekEnd)
                    ->where('end', '>=', $weekStart)
                    ->where('reason', '!=', 'Urlaub')
                    ->get();
                foreach ($employes_absences as $absence) {
                    for ($x = $weekStart->copy(); $x <= $weekEnd; $x->addDay()) {
                        if ($x->between($absence->start, $absence->end)) {
                            $roster->events()->where('employe_id', $employe->id)->where('date', $x)->update(['employe_id' => null]);
                            $roster->working_times()->where('employe_id', $employe->id)->where('date', $x)->delete();
                            $event = new RosterEvents([
                                'roster_id' => $roster->id,
                                'employe_id' => $employe->id,
                                'date' => $x->copy(),
                                'start' => '08:00:00',
                                'end' => '14:30:00',
                                'event' => $absence->reason,
                            ]);
                            $event->save();
                        }
                    }
                }
            }
        }
        return redirect(url('roster/' . $roster->id))->with('success', 'Dienstplan wurde erstellt');
    }

    /**
     * Display the specified resource.
     *
     * @param Roster $roster
     * @return View
     */
    public function show(Roster $roster)
    {
        $roster->load(['department.roster_checks','department','working_times','events']);
        $department = $roster->department;
        $weekStart = $roster->start_date->copy();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $days = [];
        for ($i=0;$i<7;$i++) { $days[] = $weekStart->copy()->addDays($i); }
        $employes = Cache::remember($roster->id.'roster_employes', 1200, function () use ($department, $weekStart, $weekEnd){
            return $department->activeEmployes($weekStart, $weekEnd);
        });
        $working_times = $roster->working_times; // eager loaded
        $events = $roster->events; // eager loaded

        // Index WorkingTimes: [employe_id][Y-m-d] => WorkingTime
        $wtIndex = [];
        foreach ($working_times as $wt) {
            $wtIndex[$wt->employe_id][$wt->date->format('Y-m-d')] = $wt;
        }
        // Index Events pro Slot (15 Min) & Start: [employe_id][Y-m-d][HH:ii]
        $eventSlotIndex = [];
        $eventStartIndex = [];
        foreach ($events as $ev) {
            if (is_null($ev->employe_id)) { // bleibt für Bookmarks separat
                continue;
            }
            $dayKey = $ev->date->format('Y-m-d');
            $emp = $ev->employe_id;
            $startTimeStr = $ev->start->format('H:i');
            $eventStartIndex[$emp][$dayKey][$startTimeStr] = $ev;
            // Slots füllen (nur 8:00-14:30 Bereich relevant)
            for ($t = $ev->start->copy(); $t->lt($ev->end); $t->addMinutes(15)) {
                $slotStr = $t->format('H:i');
                if ($slotStr < '08:00' || $slotStr >= '14:30') { continue; }
                $eventSlotIndex[$emp][$dayKey][$slotStr] = $ev;
            }
        }

        $checks = [];
        foreach ($days as $d) { $checks[$d->format('Y-m-d')] = []; }
        foreach ($department->roster_checks()->orderBy('weekday')->get() as $check) {
            $day = $weekStart->copy()->addDays($check->weekday);
            $field = $check->field_name;
            $passed = $check->check_name;
            switch ($check->type) {
                case WorkingTime::class:
                    switch ($field) {
                        case 'function':
                            $filtered = $working_times->filter(function ($working_time) use ($day, $check) {
                                return $working_time->date->equalTo($day) && $working_time->function == $check->value;
                            });
                            break;
                        default:
                            $target = Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' ' . $check->value);
                            $filtered = $working_times->filter(function ($working_time) use ($day, $field, $check, $target) {
                                if (!$working_time->date->isSameDay($day) || is_null($working_time->{$field})) { return false; }
                                $wtVal = $working_time->{$field};
                                return match($check->operator) {
                                    '<=' => $wtVal->lessThanOrEqualTo($target),
                                    '<'  => $wtVal->lessThan($target),
                                    '='  => $wtVal->equalTo($target),
                                    '>=' => $wtVal->greaterThanOrEqualTo($target),
                                    '>'  => $wtVal->greaterThan($target),
                                    default => false
                                };
                            });
                            break;
                    }
                    break;
                case RosterEvents::class:
                    $filtered = $events->filter(function ($event) use ($day, $check) {
                        return $event->date->equalTo($day) && $event->event == $check->value;
                    });
                    break;
                default:
                    $filtered = collect();
            }
            $checks[$day->format('Y-m-d')][$passed] = ($filtered->count() >= $check->needs) ? 'checked' : 'failed';
        }
        return view('personal.rosters.editRoster', compact('department','employes','roster','working_times','events','checks','days','wtIndex','eventSlotIndex','eventStartIndex'));
    }

    public function toogleDayView($roster, $day)
    {
        if (session()->exists($day)) {
            session()->remove($day);
        } else {
            session()->put($day, true);
        }
        Cache::forget('roster_'.$roster.'_'.Carbon::createFromFormat('Y-m-d',$day)->format('Ymd'));


        return redirectBack(null, null, '#' . $day);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Roster $roster
     * @return Response
     */
    public function edit(Roster $roster)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Roster $roster
     * @return Response
     */
    public function update(Request $request, Roster $roster)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Roster $roster
     * @return Response
     */
    public function destroy(Roster $roster)
    {
        if (!auth()->user()->can('create roster')) {
            return redirectBack('danger', 'Berechtigung fehlt');
        }

        // $roster->events()->delete();
        //$roster->working_times()->delete();
        $roster->delete();

        return redirectBack('warning', 'Dienstplan gelöscht');

    }

    public function exportPDF(Roster $roster)
    {
        if (auth()->user()->can('create roster') or auth()->user()->groups_rel->contains($roster->department)){
            return $this->createPDF($roster)->stream($roster->start_date->copy()->format('Y_m_d') . '_dienstplan.pdf');
        }
        return redirectBack('danger', 'Berechtigung fehlt');

    }

    public function createPDF(Roster $roster)
    {
        $weekStart = $roster->start_date->copy();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $employes = $roster->department->activeEmployes($weekStart, $weekEnd);
        $working_times = $roster->working_times; // might eager load earlier
        $events = $roster->events;
        $pdf = PDF::loadView('personal.rosters.pdf.pdf', [
            'roster' => $roster,
            'employes' => $employes,
            'working_times' => $working_times,
            'events' => $events,
            'department' => $roster->department
        ]);
        return $pdf->setOptions([
            'encoding' => 'utf-8',
            'margin-top' => '8',
            'page-size' => 'A3',
            'orientation' => 'Landscape',
        ]);
    }

    public function createPDFEmploye(Roster $roster, User $employe)
    {
        $working_times = $roster->working_times()->where('employe_id', $employe->id)->get();
        $events = $roster->events()->where('employe_id', $employe->id)->get();
        $pdf = PDF::loadView('personal.rosters.pdf.pdfEmploye', [
            'roster' => $roster,
            'working_times' => $working_times,
            'events' => $events,
            'employe' => $employe
        ]);
        return $pdf->setOptions([
            'encoding' => 'utf-8',
            'margin-top' => '10',
            'margin-bottom' => '10',
            'page-size' => 'A4',
            'orientation' => 'Landscape',
        ]);
    }

    public function sendRosterMail(Roster $roster)
    {
        $weekStart = $roster->start_date->copy();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $employes = $roster->department->activeEmployes($weekStart, $weekEnd);
        $rosterPDF = $this->createPDF($roster)->save(storage_path('dienstplan.pdf'), 1);
        $name = auth()->user()->name;
        foreach ($employes as $employe) {

            if ($employe->email) {
                $rosterEmployePDF = $this->createPDFEmploye($roster, $employe)->save(storage_path('dienstplan_' . $employe->vorname . '.pdf'), 1);
                $message = new SendRosterMail($employe->vorname, $employe->nachname, $roster->start_date->format('d.m.Y'), $name, [
                    'dienstplan.pdf', 'dienstplan_' . $employe->vorname . '.pdf'
                ]);
                Mail::to($employe->email)->queue($message);
                Storage::delete('dienstplan_' . $employe->vorname . '.pdf');
            }

        }
        Storage::delete('dienstplan.pdf');

        return redirectBack('success', 'E-Mails versandt');
    }

    public function exportPdfEmploye(Roster $roster, User $employe)
    {
        // Route erwartet diese Methode (roster.export.employe.pdf)
        return $this->createPDFEmploye($roster, $employe)->stream(
            $roster->start_date->copy()->format('Y_m_d').'_dienstplan_'.$employe->vorname.'.pdf'
        );
    }


}
