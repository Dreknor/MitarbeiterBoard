<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createRosterRequest;
use App\Mail\SendRosterMail;
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
            'templates' => $department->rosters()->where('type', 'template')->orderByDesc('start_date')->get(5)
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
        $employes = $roster->department->activeEmployes($roster->start_date, $roster->start_date->endOfWeek());

        for ($day = $roster->start_date->copy(); $day->lessThanOrEqualTo($roster->start_date->endOfWeek()); $day->addDay()) {
            if (is_holiday($day)) {
                foreach ($employes as $employe) {
                    $event = new RosterEvents([
                        'roster_id' => $roster->id,
                        'employe_id' => $employe->id,
                        'date' => $day,
                        'start' => '08:00:00',
                        'end' => '14:30:00',
                        'event' => is_holiday($day)?->title,
                    ]);
                    $event->save();
                }
            }
        }

        if ($request->used_template != null) {
            $template = Roster::findOrFail($request->used_template);
            $templateEvents = $template->events;
            $templateWorkingTimes = $template->working_times;


            foreach ($templateEvents as $event) {
                $days = $template->start_date->diffInDays($event->date);


                /* Check holiday */
                if (is_holiday($roster->start_date->addDays($days))) {
                    continue;
                }

                $newEvent = $event->replicate();
                $newEvent->roster_id = $roster->id;

                $newEvent->date = $roster->start_date->addDays($days);

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
                    $days = $template->start_date->diffInDays($workingTime->date);
                    $newWorkingTime->date = $roster->start_date->addDays($days);
                    $newWorkingTime->save();
                }
            }
        }

        foreach ($employes as $employe) {
            //urlaub eintragen
            $employes_holidays = $employe->holidays()->where('start_date', '<=', $roster->start_date->endOfWeek())->where('end_date', '>=', $roster->start_date)->get();
            for ($x = $roster->start_date->copy(); $x <= $roster->start_date->endOfWeek(); $x->addDay()) {
                $holiday = $employes_holidays->where('start_date', '<=', $x)->where('end_date', '>=', $x)->first();
                if ($holiday) {

                    $roster->events()->where('employe_id', $employe->id)->where('date', $x)->update([
                        'employe_id' => null
                    ]);

                    $roster->working_times()->where('employe_id', $employe->id)->where('date', $x)->delete();

                    $event = new RosterEvents([
                        'roster_id' => $roster->id,
                        'employe_id' => $employe->id,
                        'date' => $x,
                        'start' => '08:00:00',
                        'end' => '14:30:00',
                        'event' => $holiday->title,
                    ]);
                    $event->save();
                }

            }

            //Abwesenheiten eintragen
            $employes_absences = $employe->absences()->where('start', '<=', $roster->start_date->endOfWeek())->where('end', '>=', $roster->start_date)->get();
            foreach ($employes_absences as $absence) {
                for ($x = $roster->start_date->copy(); $x <= $roster->start_date->endOfWeek(); $x->addDay()) {
                    if ($x->between($absence->start, $absence->end)) {
                        $roster->events()->where('employe_id', $employe->id)->where('date', $x)->update([
                            'employe_id' => null
                        ]);

                        $roster->working_times()->where('employe_id', $employe->id)->where('date', $x)->delete();

                        $event = new RosterEvents([
                            'roster_id' => $roster->id,
                            'employe_id' => $employe->id,
                            'date' => $x,
                            'start' => '08:00:00',
                            'end' => '14:30:00',
                            'event' => $absence->reason,
                        ]);
                        $event->save();
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

        $department = $roster->department;
        $employes = Cache::remember($roster->id.'roster_employes', 1200, function () use ($department, $roster){
            return $department->activeEmployes($roster->start_date, $roster->start_date->endOfWeek());
        });

        $working_times = $roster->working_times;
        $events = $roster->events;

        $checks = [];

        for ($day = $roster->start_date->copy(); $day->lessThanOrEqualTo($roster->start_date->endOfWeek()); $day->addDay()) {
            $checks[$day->format('Y-m-d')] = [];
        }

        //Checks

        foreach ($department->roster_checks()->orderBy('weekday')->get() as $check) {
            $day = $roster->start_date->addDays($check->weekday);
            $field = $check->field_name;
            $operator = $check->operator;
            $passed = $check->check_name;
            switch ($check->type) {
                case WorkingTime::class:
                    switch ($field) {
                        case 'function':
                            $filtered = $working_times->filter(function ($working_time) use ($day, $field, $check) {
                                if ($working_time->date == $day and $working_time->function == $check->value) {
                                    return $working_time;
                                }
                                return false;
                            });
                            break;

                        default:
                            $dateTime = Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' ' . $check->value);

                            $filtered = $working_times->filter(function ($working_time) use ($day, $field, $check, $dateTime) {
                                switch ($check->operator) {
                                    case '<=':
                                        if ($working_time->date == $day and $working_time->$field?->lessThanOrEqualTo(Carbon::createFromFormat('Y-m-d H:i', $working_time->date->format('Y-m-d') . " " . $check->value))) {
                                            return $working_time;
                                        }
                                        break;
                                    case '<':
                                        if ($working_time->date->format('Y-m-d') == $day->format('Y-m-d') and $working_time->{$field} != null and $working_time->{$field}?->lessThanEqualTo(Carbon::createFromFormat('Y-m-d H:i', $working_time->date->format('Y-m-d') . " " . $check->value))) {
                                            return $working_time;
                                        }
                                        break;
                                    case '=':
                                        if ($working_time->date->format('Y-m-d') == $day->format('Y-m-d') and $working_time->{$field} != null and $working_time->{$field}?->EqualTo(Carbon::createFromFormat('Y-m-d H:i', $working_time->date->format('Y-m-d') . " " . $check->value))) {
                                            return $working_time;
                                        }
                                        break;
                                    case '>=':
                                        if ($working_time->date->format('Y-m-d') == $day->format('Y-m-d') and $working_time->{$field} != null and $working_time->{$field}?->greaterThanOrEqualTo(Carbon::createFromFormat('Y-m-d H:i', $working_time->date->format('Y-m-d') . " " . $check->value))) {

                                            return $working_time;
                                        }
                                        break;
                                    case '>':
                                        if ($working_time->date->format('Y-m-d') == $day->format('Y-m-d') and $working_time->{$field} != null and $working_time->{$field}?->greaterThan(Carbon::createFromFormat('Y-m-d H:i', $working_time->date->format('Y-m-d') . " " . $check->value))) {
                                            return $working_time;
                                        }
                                        break;
                                }

                                return false;
                            });
                            break;
                    }

                    break;
                    case RosterEvents::class:{
                        $filtered = $events->filter(function ($event) use ($day, $field, $check) {
                            if ($event->date == $day and $event->event == $check->value) {
                                return $event;
                            }
                            return false;
                        });
                        break;
                    }
            }

            if ($filtered->count() >= $check->needs) {
                $checks[$day->format('Y-m-d')][$passed] = 'checked';
            } else {
                $checks[$day->format('Y-m-d')][$passed] = 'failed';
            }
        }


        return view('personal.rosters.editRoster', [
            'department' => $department,
            'employes' => $employes,
            'roster' => $roster,
            'working_times' => $working_times,
            'events' => $events,
            'checks' => $checks

        ]);

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
            return $this->createPDF($roster)->stream($roster->start_date->format('Y_m_d') . '_dienstplan.pdf');
        }
        return redirectBack('danger', 'Berechtigung fehlt');

    }

    public function createPDF(Roster $roster)
    {

        $employes = $roster->department->activeEmployes($roster->start_date, $roster->start_date->endOfWeek());
        $working_times = $roster->working_times;
        $events = $roster->events;


        $pdf = PDF::loadView('personal.rosters.pdf.pdf', [
            'roster' => $roster,
            'employes' => $employes,
            'working_times' => $working_times,
            'events' => $events,
            'department' => $roster->department
        ]);

        return $pdf
            ->setOptions([
                "encoding" => "utf-8",
                'margin-top' => '10',
                'page-size' => 'A3',
                'orientation' => 'Landscape',
            ]);
    }

    public function exportPDFEmploye(Roster $roster, User $employe)
    {

        return $this->createPDFEmploye($roster, $employe)
            ->setPaper('A4','Landscape')
            ->inline($roster->start_date->format('Y_m_d') . '_dienstplan.pdf');
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
            "encoding" => "utf-8",
            'margin-top' => '10',
            'margin-bottom' => '10',
            'page-size' => 'A4',
            'orientation' => 'Landscape',
        ]);
    }

    public function sendRosterMail(Roster $roster)
    {
        $employes = $roster->department->activeEmployes($roster->start_date, $roster->start_date->endOfWeek());

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


}
