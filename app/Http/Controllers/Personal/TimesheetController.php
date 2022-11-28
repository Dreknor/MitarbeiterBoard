<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createTimesheetDayRequest;
use App\Models\Absence;
use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\personal\WorkingTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class TimesheetController extends Controller
{

    /*
    private function getGroup($old_group_id){

        return Cache::remember($old_group_id.'_gruppe', 60, function() use ($old_group_id){
            $groups_old = collect(DB::connection('dienstplan')->select("Select * from gruppe where id = $old_group_id"));
            $group = Group::firstOrCreate([
                'name'=> $groups_old->first()->gruppenname
            ]);

            return  $group->id;
        });

    }
    private function getEmploye($old_employe_id){
        if (is_null($old_employe_id)){
            return null;
        }

        return Cache::remember($old_employe_id.'_employe', 60, function() use ($old_employe_id){
            $user = DB::connection('dienstplan')->select("Select * from mitarbeiter WHERE id ='$old_employe_id' LIMIT 1");

            //dump($user[0]->vorname.' '.$user[0]->nachname);
            $users_exist = User::query()->withTrashed()->where('name', $user[0]->vorname.' '.$user[0]->nachname)->first();

            if ($users_exist == null){ //dump($user);
                }
            return $users_exist?->id;
        });

    }
    public function importEmployments(){
        if (!auth()->user()->can('edit employe')){
            return redirect()->back();
        }


       $users_exist = User::query()->withTrashed()->get();

        $groups = Group::all();

        $groups_new = [];

                //Employments
                $employments_old = collect(DB::connection('dienstplan')->select("SELECT * FROM `anstellungen` WHERE `deleted_at` IS NULL"));
                $employments_old = $employments_old->sortBy('id');

                $new_employments = [];

                foreach ($employments_old as $employment){

                    if ($this->getEmploye($employment->mitarbeiter_id) == null){
                        continue;
                    }



                    $new_employments[] = [
                        'id' => $employment->id,
                        'employe_id' => $this->getEmploye($employment->mitarbeiter_id),
                        'department_id' => $this->getGroup($employment->gruppen_id),
                        'hour_type_id'  => 1,
                        'start' => $employment->startdatum,
                        'end' => ($employment->gueltigBis != null)? $employment->gueltigBis :$employment->enddatum,
                        'hours' => $employment->stunden,
                        'comment' => $employment->grund,
                        'deleted_at' => ($employment->stunden == 0)? (($employment->gueltigBis != null)? $employment->gueltigBis :$employment->enddatum) : null,
                    ];
                }

                Employment::insert($new_employments);

                return redirect(url('timesheets/import/roster'));
    }
    public function getTimesheets($day, $user)
    {
        $day = Carbon::createFromFormat('Y-m-d', $day);
        return Cache::remember('timesheets_'.$user.'_'.$day->format('Y-m'), 60, function () use ($user, $day){
            return Timesheet::firstOrCreate([
                'month' => $day->month,
                'year' => $day->year,
                'employe_id' => $user
            ],[
                'working_time_account' =>0
            ]);
        });
    }
    public function importRoster($year = 2005){
        $rosters_old = collect(DB::connection('dienstplan')->select("SELECT * FROM `dienstplan` WHERE `startdatum` BETWEEN '".$year."-01-01' AND '".$year."-12-31' "));
        $new_roster = [];

        foreach ($rosters_old as $roster){

                $new_roster[] = [
                    'id' => $roster->id,
                    'start_date' => $roster->startdatum,
                    'type' => $roster->typ,
                    'comment' => $roster->kommentar,
                    'department_id' => $this->getGroup($roster->gruppe_id)
                ];
        }

        Roster::insert($new_roster);

        $rosters_old = null;

        $events_old = collect(DB::connection('dienstplan')->select("SELECT * FROM `termine` WHERE `datum` BETWEEN '$year-01-01' AND '$year-12-31'"));
        $new_events = [];
        dump(count($events_old));
        foreach ($events_old as $event){
            if ($this->getEmploye($event->mitarbeiter) != null){
                $new_events[]=[
                    'event' => $event->terminname,
                    'roster_id' => $event->dienstplan_id,
                    'employe_id' => $this->getEmploye($event->mitarbeiter),
                    'date' => $event->datum,
                    'start' => $event->anfangszeit,
                    'end' => $event->endzeit,
                    'id' => $event->id
                ];
            }
        }
        dump(count($new_events));
        RosterEvents::insert($new_events);
        $new_workingtimes = null;

        $workingtime_old = collect(DB::connection('dienstplan')->select("SELECT * FROM `arbeitszeit` WHERE `datum` BETWEEN '$year-01-01' AND '$year-12-31' "));
        $new_workingtimes = [];
        foreach ($workingtime_old as $workingtime){
            if ($this->getEmploye($workingtime->mitarbeiter_id) != null){

                $new_workingtimes[]=[
                    'roster_id' => $workingtime->dienstplan_id,
                    'employe_id' => $this->getEmploye($workingtime->mitarbeiter_id),
                    'date' => $workingtime->datum,
                    'start' => $workingtime->anfangszeit,
                    'end' => $workingtime->endzeit,
                    'function' => $workingtime->aufgabe,
                ];
            }
        }
        WorkingTime::insert($new_workingtimes);

        dump($year);
        if ($year != 2023){
            $timesheets = Timesheet::query()->where('year', $year)->get();
            foreach ($timesheets as $timesheet){
                $timesheet->updateTime();
            }
            dump($year);
            return redirect(url('http://mitarbeiter.local/timesheets/import/roster/'.$year+1));
        } else
        {
            return redirect(url('timesheets/import/2015'));
        }
    }
    public function import($year){
        if (!auth()->user()->can('edit employe')){
            return redirect()->back();
        }


        $users = collect(DB::connection('dienstplan')->select('Select * from mitarbeiter'));

        $users_exist = User::query()->withTrashed()->get();
        $users_new = [];
        foreach ($users as $user){
            $user_new = $users_exist->first(function ($item) use ($user){
                return $item->name == $user->vorname.' '.$user->nachname;
            });
            if ($user_new == null){
                continue;
            }
            $users_new[$user->id] = $user_new->id;
        }

        $users_exist = null;



        //Arbeitszeitnachweise

       $arbeitszeitnachweis = collect(DB::connection('dienstplan')->select("SELECT * FROM `arbeitszeitnachweis` WHERE `datum` BETWEEN '$year-01-01' AND '$year-12-31' ORDER BY `id` DESC"));

        $new_arbeitszeitnachweis = [];
        foreach ($arbeitszeitnachweis as $key => $nachweis){
            set_time_limit(60);
            if ($nachweis->deleted_at == null){

                if (!array_key_exists($nachweis->mitarbeiter_id, $users_new)){
                    continue;
                }
                $timesheet = $this->getTimesheets($nachweis->datum, $users_new[$nachweis->mitarbeiter_id]);

                if (!is_null($nachweis->bemerkung) and array_key_exists($nachweis->bemerkung, config('config.abwesenheiten_arbeitszeit'))){
                    $new_arbeitszeitnachweis[] = [
                        'timesheet_id' => $timesheet->id,
                        'date' => $nachweis->datum,
                        'start' => null,
                        'end' => null,
                        'pause' => null,
                        'percent_of_workingtime' => config('config.abwesenheiten_arbeitszeit')[$nachweis->bemerkung],
                        'comment' => $nachweis->bemerkung,
                    ];

                } else {
                    $new_arbeitszeitnachweis[] = [
                        'timesheet_id' => $timesheet->id,
                        'date' => $nachweis->datum,
                        'start' => $nachweis->arbeitsbeginn,
                        'end' => $nachweis->arbeitsende,
                        'pause' => $nachweis->pause,
                        'percent_of_workingtime' => null,
                        'comment' => $nachweis->bemerkung,
                    ];

                }




                if ($nachweis->zusatzstunden != null){
                    $start = Carbon::createFromFormat('Y-m-d H:i', $nachweis->datum.' '.'00:00');
                    $new_arbeitszeitnachweis[] = [
                        'timesheet_id' => $timesheet->id,
                        'date' => $nachweis->datum,
                        'start' => $start->format('H:i'),
                        'end' => $start->addHours(Str::before($nachweis->zusatzstunden, ':'))->addMinutes(Str::after($nachweis->zusatzstunden, ':')),
                        'comment' => 'Übernahme Zusatzstunden Dienstplanverwaltun',
                        'pause' => 0,
                        'percent_of_workingtime' => null,
                    ];
                }
            }
        }

        TimesheetDays::insert($new_arbeitszeitnachweis);

        if ($year != 2023){
            $timesheets = Timesheet::query()->where('year', $year)->get();
            foreach ($timesheets as $timesheet){
                $timesheet->updateTime();
            }
            dump($year);
            return redirect(url('http://mitarbeiter.local/timesheets/import/'.$year+1));
        } else
        {
            return redirect(url('http://mitarbeiter.local/'));
        }
    }
    */
    /**
     * Display a listing of the resource.
     *
     * @return RedirectResponse | View
     */
    public function index()
    {
        if (!auth()->user()->can('edit employe') and auth()->user()->can('has timesheet')){
            return redirect(url('timesheets/'.auth()->id()));
        }
        if (!auth()->user()->can('edit employe') and !auth()->user()->can('has timesheet')){
            return redirect()->back();
        }

        return view('personal.timesheets.selectEmploye', [
            'employes' => User::all()
        ]);
    }



    public function storeDay(createTimesheetDayRequest $request, User $user, Timesheet $timesheet, $day){
        if (!auth()->user()->can('edit employe') and auth()->user()->can('has timesheet')){
            return redirect(url('timesheets/'.auth()->id()));
        }
        if (!auth()->user()->can('edit employe') and !auth()->user()->can('has timesheet')){
            return redirect()->back();
        }
        $day = Carbon::createFromFormat('Y-m-d', $day);
        $timesheetDay = new TimesheetDays($request->validated());
        $timesheetDay->timesheet_id=$timesheet->id;
        $timesheetDay->date=$day;
        $timesheetDay->save();

        $timesheet->updateTime();

        return redirect(url('timesheets/'.$user->id.'/'.$day->format('Y-m')))->with(['success', 'Arbeitszeit gespeichert']);

    }

    public function addFromAbsence(User $user, Timesheet $timesheet, $day, $absence){
        if (!auth()->user()->can('edit employe') and auth()->user()->can('has timesheet')){
            return redirect(url('timesheets/'.auth()->id()));
        }
        if (!auth()->user()->can('edit employe') and !auth()->user()->can('has timesheet')){
            return redirect()->back();
        }
        if( !array_key_exists($absence, config('config.abwesenheiten_arbeitszeit'))){
            return redirectBack('warning', 'Fehler bei der Auswahl');
        }
        $day = Carbon::createFromFormat('Y-m-d', $day);
        $timesheetDay = new TimesheetDays([
            'percent_of_workingtime' => config("config.abwesenheiten_arbeitszeit.$absence"),
            'comment' => $absence
        ]);

        $timesheetDay->timesheet_id=$timesheet->id;
        $timesheetDay->date=$day;
        $timesheetDay->save();

        $timesheet->updateTime();

        return redirect(url('timesheets/'.$user->id.'/'.$day->format('Y-m')))->with(['success', 'Arbeitszeit gespeichert']);

    }


    public function deleteDay(User $user, Timesheet $timesheet, TimesheetDays $timesheetDay){
        if (!auth()->user()->can('edit employe') and ($user->id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back();
        }

        if ($timesheetDay->timesheet_id == $timesheet->id and $timesheet->employe_id == $user->id){
            $timesheetDay->delete();
            $timesheet->updateTime();
            return redirectBack('success', 'Eintrag gelöscht');
        }
        return redirectBack('warning', 'Fehler bei der Zuordnung');
    }

    /**
     * add new Day
     *
     */

    public function addDay(User $user, Timesheet $timesheet, $day){
        if (!auth()->user()->can('edit employe') and ($user->id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back();
        }

        $day = Carbon::createFromFormat('Y-m-d', $day);

        return view('personal.timesheets.addDay',[
            'day' => $day,
            'user' => $user,
            'timesheet' => $timesheet
        ]);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\personal\Timesheet  $timesheet
     * @return RedirectResponse | View
     */
    public function show(User $user, $date = null)
    {
        if (!auth()->user()->can('edit employe') and ($user->id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back();
        }

        if ($date == null){
           $act_month = Carbon::today();
        } else {
            $act_month = Carbon::createFromFormat('Y-m', $date);
        }

        //keine Anstellung in diesem Monat
        if ($user->employments_date($act_month)->count() <1){
            return redirectBack('warning', 'Keine Anstellung in dem gewählten Monat');
        }
        //nur bis aktuellem Monat
        if ($act_month->copy()->endOfMonth()->greaterThan(Carbon::today()->endOfMonth())){
            return redirectBack('warning', 'Dieses Datum liegt in der Zukunft');
        }

        $old = $act_month->copy()->subMonth();
        $timesheet_old = Cache::remember('timesheet_'.$user->id.'_'.$old->year.'_'.$old->month, 60, function () use ($user, $old){
            return Timesheet::where('employe_id', $user->id)
                ->where('year', $old->year)
                ->where('month', $old->month)
                ->first();
        });

        $timesheet = Timesheet::firstOrCreate([
                'employe_id' => $user->id,
                'year' => $act_month->year,
                'month' => $act_month->month,
            ], [
            'working_time_account' => 0
        ]);

        if ($timesheet->wasRecentlyCreated === true or $timesheet->timesheet_days->count() == null){
            $working_times = $user->working_times->filter(function ($working_time) use ($act_month){
                return $working_time->date->greaterThanOrEqualTo($act_month->startOfMonth()) and $working_time->date->lessThanOrEqualTo($act_month->endOfMonth());
            });

            $newTimesheetDays = [];

            //Pausen holen
            $pausen = RosterEvents::where('employe_id', $user->id)
                ->where('event', 'LIKE', 'pause%')
                ->whereBetween('date', [$act_month->copy()->startOfMonth()->format('Y-m-d'),$act_month->copy()->endOfMonth()->format('Y-m-d')])
                ->get();

            foreach ($working_times as $working_time){
                if ($working_time->start != null and $working_time->end != null){
                    $pause = $pausen->filter(function ($event) use($working_time){
                        return $event->date->format('Y-m-d') == $working_time->date->format('Y-m-d');
                    });
                    $newTimesheetDays[]=[
                        'timesheet_id' => $timesheet->id,
                        'date'  => $working_time->date,
                        'start' => $working_time->start,
                        'end' => $working_time->end,
                        'pause' => $pause?->sum('duration')
                    ];
                }
            }

            $config= config('config.abwesenheiten_arbeitszeit');
            //Abwesenheiten
            $absences = Absence::whereIn('reason', array_keys($config))
                ->where('users_id', $user->id)
                ->whereDate('start', '>=', $act_month->copy()->startOfMonth()->format('Y-m-d'))
                ->whereDate('end', '<=', $act_month->copy()->endOfMonth()->format('Y-m-d'))
                ->get();
            TimesheetDays::insert($newTimesheetDays);

            $newTimesheetDays = [];

            foreach ($absences as $absence){
                for ($day=$absence->start; $day->lessThanOrEqualTo($absence->end); $day->addDay()){
                    $newTimesheetDays[]=[
                        'timesheet_id' => $timesheet->id,
                        'date'  => $day->format('Y-m-d'),
                        'percent_of_workingtime' => $config[$absence->reason],
                        'comment' => $absence->reason
                    ];
                }
            }

            TimesheetDays::insert($newTimesheetDays);


        }

        $timesheet_days = $timesheet?->timesheet_days;

        $timesheet->updateTime();

        return view('personal.timesheets.timesheet', [
            'timesheet_old' => $timesheet_old,
            'timesheet' => $timesheet,
            'timesheet_days' => $timesheet_days,
            'employe' => $user,
            'month' => $act_month
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\personal\Timesheet  $timesheet
     * @return \Illuminate\Http\Response
     */
    public function edit(Timesheet $timesheet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\personal\Timesheet  $timesheet
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Timesheet $timesheet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\personal\Timesheet  $timesheet
     * @return \Illuminate\Http\Response
     */
    public function destroy(Timesheet $timesheet)
    {
        //
    }

    public function updateTimesheets(User $user){
        if (!auth()->user()->can('edit employe') and auth()->user()->can('has timesheet')){
            return redirect(url('timesheets/'.auth()->id()));
        }

        $timesheets = $user->timesheets;
        $timesheets = $timesheets->sortBy([
            ['year', 'asc'],
            ['month', 'asc'],
        ]);


        foreach ($timesheets as $timesheet){
            $timesheet->updateTime();
        }
        return redirectBack('success', 'Aktualisierung erfolgreich');
    }
}

