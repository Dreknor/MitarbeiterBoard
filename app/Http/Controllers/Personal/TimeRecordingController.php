<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\checkTimeRecordingPinRequest;
use App\Http\Requests\getTimeRecordingKeyRequest;
use App\Http\Requests\storeSecretKeyRequest;
use App\Models\personal\EmployeData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TimeRecordingController extends Controller
{
    public function checkin_checkout()
    {
        if (!auth()->user() or !auth()->user()->can('has timesheet')){
                return redirect()->route('home')->with(
                [
                    'type'=>'warning',
                    'Meldung'=>'Keine Berechtigung'
                ]
            );
        }

        $timesheet = auth()->user()->timesheets()->where([
            'month'=>now()->month,
            'year'=>now()->year
        ])->first();

        if (is_null($timesheet)){
            $latest = auth()->user()->timesheets()->orderByDesc('year')->orderByDesc('month')->first();
            $timesheet = auth()->user()->timesheets()->create([
                'month'=>now()->month,
                'year'=>now()->year,
                'holidays_old' => $latest->holidays_old + $latest->holidays_new,
                'working_time_account' => $latest->working_time_account,
            ]);
        }

        $timesheet_day = $timesheet->timesheet_days()->whereDate('date', now()->format('Y-m-d'))->orderBy('end')->first();

        if (is_null($timesheet_day)){
            $timesheet_day = $timesheet->timesheet_days()->create([
                'date' => now()->format('Y-m-d'),
                'start' => now(),
                'timesheet_id' => $timesheet->id,
                'comment' => 'digitale Zeiterfassung'
            ]);
        } elseif (!is_null($timesheet_day) and is_null($timesheet_day->end)){
            $timesheet_day->update([
                'end' => now()
            ]);

            $timesheet->updateTime();
        } else {
            $timesheet_day = $timesheet->timesheet_days()->create([
                'date' => now()->format('Y-m-d'),
                'start' => now(),
                'timesheet_id' => $timesheet->id,
                'comment' => 'digitale Zeiterfassung'
            ]);
        }

        return redirect()->route('home')->with(
            [
                'type'=>'success',
                'Meldung'=>'Erfolgreich eingestempelt'
            ]
        );

    }

    //
    public function start()
    {
        return view('personal.time_recording.start');
    }


    public function storeSecret(storeSecretKeyRequest $request)
    {
        $key = Cache::get('time_recording_key');

        if (is_null($key)){
            return redirect()->route('time_recording.logout')->with(
                [
                    'type'=>'warning',
                    'Meldung'=>'Ungültiger Schlüssel'
                ]
            );
        }

        $user = EmployeData::query()->where('time_recording_key', $key)->first();
        $user->update([
            'secret_key' => $request->secret_key
        ]);
        return redirect()->route('time_recording.start')->with(
            [
                'type'=>'success',
                'Meldung'=>'Geheimcode erfolgreich gespeichert'
            ]
        );
    }

    public function read_key(getTimeRecordingKeyRequest $request)
    {

        $user = EmployeData::query()->where('time_recording_key', $request->key)->first();

        if (!$user) {
            return redirect()->back()->withErrors(['key' => 'Ungültiger Schlüssel']);
        }

        Cache::add('time_recording_key', $request->key, now()->addMinutes(2) );

        if (is_null($user->secret_key)){
            return view('personal.time_recording.set_secret', [
                'user' => $user->user
            ]);
        }



        return view('personal.time_recording.get_secret', [
            'user' => $user->user
        ]);
    }

    public function login(checkTimeRecordingPinRequest $request){
        $key = Cache::get('time_recording_key');
        $user = EmployeData::query()->where([
            'time_recording_key'=> $key,
            'secret_key'=>$request->secret_key
            ])->first();


        if (is_null($user)){
            Cache::forget('time_recording_key');
            return redirect()->back()->with(
                [
                    'type'=>'warning',
                    'Meldung'=>'Ungültiger Schlüssel oder Geheimcode'
                ]
            );
        }

        $timesheet = $user->user->timesheets()->where([
            'month'=>now()->month,
            'year'=>now()->year
        ])->first();

        if (is_null($timesheet)){
            $latest = $user->user->timesheets()->orderByDesc('year')->orderByDesc('month')->first();
            $timesheet = $user->user->timesheets()->create([
                'month'=>now()->month,
                'year'=>now()->year,
                'holidays_old' => $latest->holidays_old + $latest->holidays_new,
                'working_time_account' => $latest->working_time_account,
            ]);
        }

        $timesheet_day = $timesheet->timesheet_days()->whereDate('date', now()->format('Y-m-d'))->orderBy('end')->first();

        if (is_null($timesheet_day)){
            $timesheet_day = $timesheet->timesheet_days()->create([
                'date' => now()->format('Y-m-d'),
                'start' => now(),
                'timesheet_id' => $timesheet->id,
                'comment' => 'digitale Zeiterfassung'
            ]);
        } elseif (!is_null($timesheet_day) and is_null($timesheet_day->end)){
            $timesheet_day->update([
                'end' => now()
            ]);

            $timesheet->updateTime();
        } else {
            $timesheet_day = $timesheet->timesheet_days()->create([
                'date' => now()->format('Y-m-d'),
                'start' => now(),
                'timesheet_id' => $timesheet->id,
                'comment' => 'digitale Zeiterfassung'
            ]);
        }




        return view('personal.time_recording.login', [
            'user'=>$user->user,
            'timesheet_day'=>$timesheet_day,
            'timesheet'=>$timesheet,
            'dayBefore' => $timesheet->timesheet_days()->whereDate('date', now()->subDay()->format('Y-m-d'))->where('end', null)->first(),
        ]);
    }

    public function logout(){
        Cache::forget('time_recording_key');
        return redirect()->route('time_recording.start');
    }


}
