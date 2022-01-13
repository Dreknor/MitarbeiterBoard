<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAbsenceRequest;
use App\Mail\DailyAbsenceReport;
use App\Mail\NewAbsenceMail;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class AbsenceController extends Controller
{
    public function store(CreateAbsenceRequest $request){

        $absence = Absence::whereDate('end', '>=', Carbon::parse($request->start)->subDay())->where('users_id', $request->users_id)->first();

        if (is_null($absence)){
            $absence = new Absence($request->validated());
            if (!auth()->user()->can('create absences')){
                $absence->users_id = auth()->id();
            }
            $absence->save();

            $users = User::where('absence_abo_now', 1)->get();
        } else {
            $absence->update([
                'end' => $request->end
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
            Mail::to($user)
                ->queue(new DailyAbsenceReport($absences));
        }


    }

    public function delete(Absence $absence){
        if (auth()->user()->can('delete absences')){
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
}
