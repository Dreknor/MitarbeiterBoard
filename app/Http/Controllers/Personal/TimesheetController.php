<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createTimesheetDayRequest;
use App\Http\Requests\updateTimesheetDayRequest;
use App\Mail\SendMonthlyTimesheetMail;
use App\Models\Absence;
use App\Models\personal\RosterEvents;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use App\Notifications\Push;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class TimesheetController extends Controller
{

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

        $users = User::whereHas('employments')->get();

        foreach ($users as $key => $user){
           if (!$user->can('has timesheet')){
            $users->forget($key);
           }
        }

        return view('personal.timesheets.selectEmploye', [
            'employes' => $users
        ]);
    }



    public function storeDay(createTimesheetDayRequest $request, User $user, Timesheet $timesheet, $day){
        if (!auth()->user()->can('edit employe') and auth()->id() != $user->id){
            return redirect(url('timesheets/'.auth()->id()))->with([
                'type' => 'error',
                'Meldung' => 'falscher Benutzer']
            );
        }
        if (!auth()->user()->can('edit employe') and !auth()->user()->can('has timesheet')){
            return redirect()->back()->with([
                    'type' => 'error',
                    'Meldung' => 'keine Berechtigung']
            );
        }
        $day = Carbon::createFromFormat('Y-m-d', $day);
        $timesheetDay = new TimesheetDays($request->validated());
        $timesheetDay->timesheet_id=$timesheet->id;
        $timesheetDay->date=$day;
        $timesheetDay->save();

        $timesheet->updateTime();

        return redirect(url('timesheets/'.$user->id.'/'.$day->format('Y-m').'#'.$day->copy()->startOfWeek()->format('Y-m-d')))->with(['success', 'Arbeitszeit gespeichert']);

    }

    public function addFromAbsence(User $user, Timesheet $timesheet, $day, $absence){
        if (!auth()->user()->can('edit employe') and auth()->id() != $user->id){
            return redirect(url('timesheets/'.auth()->id()))->with([
                    'type' => 'error',
                    'Meldung' => 'falscher Benutzer']
            );
        }
        if (!auth()->user()->can('edit employe') and !auth()->user()->can('has timesheet')){
            return redirect()->back()->with([
                    'type' => 'error',
                    'Meldung' => 'keine Berechtigung']
            );
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

        return redirect(url('timesheets/'.$user->id.'/'.$day->format('Y-m').'#'.$day->copy()->startOfWeek()->format('Y-m-d')))->with(['success', 'Arbeitszeit gespeichert']);

    }


    public function deleteDay(User $user, Timesheet $timesheet, TimesheetDays $timesheetDay){
        if (!auth()->user()->can('edit employe') and ($user->id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back();
        }

        $day = $timesheetDay->date;

        if ($timesheetDay->timesheet_id == $timesheet->id and $timesheet->employe_id == $user->id){
            $timesheetDay->delete();
            $timesheet->updateTime();
            return redirect(url('timesheets/'.$timesheet->employe_id.'/'.$day->format('Y-m').'#'.$day->copy()->startOfWeek()->format('Y-m-d')))->with('success', 'Eintrag gelöscht');
        }
        return redirectBack('warning', 'Fehler bei der Zuordnung');
    }

    /**
     * add new Day
     *
     */

    public function addDay(User $user, Timesheet $timesheet, $day){
        if (!auth()->user()->can('edit employe') and auth()->id() != $user->id){
            return redirect(url('timesheets/'.auth()->id()))->with([
                    'type' => 'error',
                    'Meldung' => 'falscher Benutzer']
            );
        }
        if (!auth()->user()->can('edit employe') and !auth()->user()->can('has timesheet')){
            return redirect()->back()->with([
                    'type' => 'error',
                    'Meldung' => 'keine Berechtigung']
            );
        }

        $day = Carbon::createFromFormat('Y-m-d', $day);

        return view('personal.timesheets.addDay',[
            'day' => $day,
            'user' => $user,
            'timesheet' => $timesheet
        ]);

    }

    public function editDay(TimesheetDays $timesheetDay){
        if (!auth()->user()->can('edit employe') and ($timesheetDay->timesheet->employe_id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back();
        }

        return view('personal.timesheets.editDay',[
            'timesheet_day' => $timesheetDay,
            'day' => $timesheetDay->date,
        ]);
    }

    public function updateDay(updateTimesheetDayRequest $request, TimesheetDays $timesheetDay){
        if (!auth()->user()->can('edit employe') and ($timesheetDay->timesheet->employe_id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back();
        }

        $timesheetDay->update($request->validated());

        $timesheetDay->timesheet->updateTime();

        return redirect(url('timesheets/'.$timesheetDay->timesheet->employe_id.'/'.$timesheetDay->date->format('Y-m').'#'.$timesheetDay->date->copy()->startOfWeek()->format('Y-m-d')))->with('success', 'Eintrag aktualisiert');
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
                if ($working_time->roster?->type != 'Vorlage'){
                    return $working_time->date->greaterThanOrEqualTo($act_month->startOfMonth()) and $working_time->date->lessThanOrEqualTo($act_month->endOfMonth());
                }
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
            'balance' => $timesheet->working_time_account,
            'employe' => $user,
            'month' => $act_month
        ]);

    }

    public function export(User $user, Timesheet $timesheet)
    {
        if (!auth()->user()->can('edit employe') and ($user->id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back();
        }


            $act_month = Carbon::createFromFormat('Y-m', $timesheet->year.'-'.$timesheet->month);


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
            ]);

        $timesheet_days = $timesheet->timesheet_days;

        $pdf = PDF::loadView('personal.timesheets.pdf', [
            'timesheet_old' => $timesheet_old,
            'timesheet' => $timesheet,
            'timesheet_days' => $timesheet_days,
            'employe' => $user,
            'month' => $act_month
        ]);
        return $pdf->download('AZN_'.$user->familienname.'_'.$timesheet->year.'_'.$timesheet->month.'.pdf');
    }

    public function timesheet_mail()
    {
        foreach (User::all() as $user){
            set_time_limit(60);
            if ($user->can('has timesheet') and $user->employments_date(Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth())->count() > 0){
                try {
                    if (!is_null($user->employe_data) and $user->employe_data->mail_timesheet){
                        $date = Carbon::now()->subMonth();
                        $timesheet = Timesheet::where([
                            'employe_id' => $user->id,
                            'year' => $date->year,
                            'month' => $date->month,
                        ])->first();
                        if (!is_null($timesheet)) {
                            $timesheet_days = $timesheet->timesheet_days;

                            $old = $date->copy()->subMonth();

                            $timesheet_old = Cache::remember('timesheet_' . $user->id . '_' . $old->year . '_' . $old->month, 60, function () use ($user, $old) {
                                return Timesheet::where('employe_id', $user->id)
                                    ->where('year', $old->year)
                                    ->where('month', $old->month)
                                    ->first();
                            });

                            $pdf = PDF::loadView('personal.timesheets.pdf', [
                                'timesheet' => $timesheet,
                                'timesheet_old' => $timesheet_old,
                                'timesheet_days' => $timesheet_days,
                                'employe' => $user,
                                'month' => $date
                            ]);

                            $pdf->save(storage_path('timesheet.pdf'), 1);

                            try {
                                Mail::to($user->email)->send(new SendMonthlyTimesheetMail($user, $date));

                            } catch (\Exception $e) {
                                Log::alert('Arbeitszeitnachweis-Mail konnte nicht versendet werden: ' . $e->getMessage());
                            }

                            if (File::exists(storage_path('timesheet.pdf'))) {
                                File::delete(storage_path('timesheet.pdf'));
                            }
                        }

                    } else {
                        Log::info('Keine Benachritigung für '.$user->name.' '.$user->familienname);
                    }
                } catch (\Exception $e) {
                    $admin = User::whereHas('roles', function ($query) {
                        $query->where('name', 'admin');
                    })->first();

                    $admin->notify(new Push('Fehler beim Versenden des Arbeitszeitnachweises', 'Fehler beim Versenden des Arbeitszeitnachweises für ' . $user->name . ' ' . $user->familienname . ' ' . $date->format('Y-m')));

                    continue;
                }

            }
        }

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
     * @return RedirectResponse
     */
    public function updateSheet(User $user,  Timesheet $timesheet)
    {

        if (!auth()->user()->can('edit employe') and ($user->id != auth()->id() and auth()->user()->can('has timesheet'))){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => "Zigriff verweigert"
            ]);
        }

        $timesheet->updateTime();

        return redirectBack('success', 'Aktuslisierung erfolgt');
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

    public function lock(User $user, Timesheet $timesheet){
        if (!auth()->user()->can('edit employe') and (auth()->id() != $timesheet->employe_id or $user->id != $timesheet->employe_id)){
            return redirectBack('warning', 'Recht fehlt');
        }

        $timesheet->update([
            'locked_at' => Carbon::now(),
            'locked_by' => auth()->id()
        ]);

        return redirectBack('success', 'Nachweis gespeichert und geschlossen');


    }
    public function unlock(User $user, Timesheet $timesheet){
        if (!auth()->user()->can('edit employe') and (auth()->id() != $timesheet->employe_id or $user->id != $timesheet->employe_id)){
            return redirectBack('warning', 'Recht fehlt');
        }

        $timesheet->update([
            'locked_at' => null,
            'locked_by' => null
        ]);

        return redirectBack('success', 'Sperre aufgehoben');


    }

    public function overviewTimesheetsUser (User $user){
        if (!auth()->user()->can('edit employe') and (auth()->id() != $user->id)){
            return redirectBack('warning', 'Recht fehlt');
        }

        return \view('personal.timesheets.overview', [
            'user' => $user,
            'timesheets' => $user->timesheets->sortBy(['year', 'month'])
        ]);
    }
}

