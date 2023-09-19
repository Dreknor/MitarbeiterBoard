<?php

namespace App\Http\Controllers;

use App\Exports\AbsenceExport;
use App\Http\Requests\CreateAbsenceRequest;
use App\Mail\DailyAbsenceReport;
use App\Mail\NewAbsenceMail;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class AbsenceController extends Controller
{
    public function store(CreateAbsenceRequest $request){

        $absence = Absence::whereDate('end', '>=', Carbon::parse($request->start)->subDay())
            ->where('users_id', $request->users_id)
            ->where('reason', $request->reason)
            ->first();

        if (is_null($absence)){
            $absence = new Absence($request->validated());
            if (!auth()->user()->can('create absences')){
                $absence->users_id = auth()->id();
            }
            $absence->save();


        } else {
            $absence->update([
                'end' => $request->end,
                'sick_note_required' => $request->sick_note_required,
            ]);
        }
        $users = User::where('absence_abo_now', 1)->get();
        foreach ($users as $user){
            $mail = Mail::to($user)->queue(new NewAbsenceMail($absence->user->name,$absence->start->format('d.m.Y'),$absence->end->format('d.m.Y'),$absence->reason));
        }

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Abwesenheit wurde gespeichert.'
        ]);
    }

    public function abo($type){
        $user = auth()->user();
        if ($type == 'daily'){
            if ($user->absence_abo_daily == 1){
                $user->update(['absence_abo_daily' => 0]);
            } else {
                $user->update(['absence_abo_daily' => 1]);
            }
        }
        if ($type == 'now'){
            if (auth()->user()->absence_abo_now == 1){
                auth()->user()->update(['absence_abo_now' => 0]);
            } else {
                auth()->user()->update(['absence_abo_now' => 1]);
            }
        }
        return redirect()->back();
    }

    public function dailyReport(){

        $absences = Absence::where('start', '<=', Carbon::now()->format('Y-m-d'))
                            ->where('end', '>=', Carbon::now()->format('Y-m-d'))
                            ->get();

        $users = User::where('absence_abo_now', 1)->get();

        foreach ($users as $user){
            $absence_user = Absence::where('start', '<=', \Illuminate\Support\Carbon::now()->format('Y-m-d'))
                ->where('end', '>=', \Carbon\Carbon::now()->format('Y-m-d'))
                ->where('users_id', $user->id)
                ->first();

            if (is_null($absence_user)) {
                Mail::to($user)
                    ->queue(new DailyAbsenceReport($absences));
            }
        }


    }

    public function delete(Absence $absence){
        if ((auth()->user()->can('delete absences') or auth()->id() == $absence->creator_id) and $absence->end->greaterThan(Carbon::tomorrow()) ){
            $absence->delete();
            return redirect()->back()->with([
                'type' => 'info',
                'Meldung' => 'Abwesenheitsmitteilung gelöscht'
            ]);
        }

        return redirect()->back()->with([
            'type' => 'danger',
            'Meldung' => 'Berechtigung fehlt'
        ]);
    }

    public function export (){
        if (!auth()->user()->can('export absence')){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        return Excel::download(new AbsenceExport(), 'Abwesenheiten.xlsx');

    }

    public function sick_notes_index() {
        if (!auth()->user()->can('manage sick_notes')){
            return redirect(url('/'))->with([
                'type'  => "warning",
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $absences = Absence::where(function ($query){
            $query->whereIn('reason', config('absences.absence_sick_note'))
                ->orWhere('sick_note_required', 1);
        })->whereDate('start', '>=', Carbon::now()->subYear())
            ->orderByDesc('start')->with('user')->get();

        $users_absences = $absences->groupBy('users_id');
        $users = new Collection();

dump($users);
        foreach ($users_absences as $absences_user){
            $without_note = 0;
            $with_note = 0;
            $missing_note = 0;

            foreach ($absences_user as $absence){
                if ($absence->days < settings('absences.absence_sick_note_days') and $absence->sick_note_required == false)
                {
                    $without_note+=$absence->days;
                }
                if (($absence->days >= config('absences.absence_sick_note_days') or $absence->sick_note_required != false) and is_null($absence->sick_note_date))
                {
                    $missing_note+=$absence->days;
                }
                if (($absence->days >= config('absences.absence_sick_note_days') or $absence->sick_note_required != false) and !is_null($absence->sick_note_date))
                {
                    $with_note+=$absence->days;
                }
            }


            $users->add([
                'user' => $absence->user->name,
                'without_note' => $without_note,
                'with_note' => $with_note,
                'missing_note' => $missing_note,
            ]);

        }

dd($users);
        return view('absences.sicknotes',[
           'absences' => $absences,
            'users' => $users->sortBy('user')
        ]);
    }

    public function sick_notes_update(Absence $absence) {
        if (!auth()->user()->can('manage sick_notes')){
            return redirect(url('/'))->with([
                'type'  => "warning",
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $absence->update([
            'sick_note_date' => Carbon::now()
        ]);

        return redirect()->back()->with([
            'type'  => "success",
            'Meldung' => 'Krankenschein erfasst für '.$absence->user->name.' ('.$absence->start->format('d.m.Y').' - '.$absence->end->format('d.m.Y').')'
        ]);
    }

    public function sick_notes_remove(Absence $absence) {
        if (!auth()->user()->can('manage sick_notes')){
            return redirect(url('/'))->with([
                'type'  => "warning",
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $absence->update([
            'sick_note_date' => null
        ]);

        return redirect()->back()->with([
            'type'  => "success",
            'Meldung' => 'Krankenschein entfernt für '.$absence->user->name.' ('.$absence->start->format('d.m.Y').' - '.$absence->end->format('d.m.Y').')'
        ]);
    }
}

