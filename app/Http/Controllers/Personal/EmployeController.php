<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\personal\CreateEmployeRequest;
use App\Http\Requests\personal\selfUpdateProfileRequest;
use App\Http\Requests\UpdateEmployeDataRequest;
use App\Models\Group;
use App\Models\personal\EmployeData;
use App\Models\personal\EmployeHolidayClaim;
use App\Models\personal\HourType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use function PHPUnit\Framework\greaterThanOrEqual;

class EmployeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        return view('personal.employes.index', [
           'employes' => User::all()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View|RedirectResponse
     */
    public function create()
    {
        if (!auth()->user()->can('create employe')){
            return redirect()->back()->with([
               'type'   => 'warning',
               'Meldung' => 'Berechtigung fehlt'
            ]);
        }
        return view('employes.create');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $employe
     * @return View
     */
    public function show(User $employe)
    {

        if (is_null($employe->employe_data)){
            $employe->employe_data()->create([
                'familienname' => Str::afterLast($employe->name, ' '),
                'vorname' => Str::before($employe->name, ' '),
                'user_id' => $employe->id,
                'geschlecht' => 'anderes',
                'mail_timesheet' => 0
            ]);
        }

        //$employments = $employe->employments()->active()->get()->sortByDesc('start');
        $employments = $employe->employments->filter(function ($employment){
            return $employment->end == null or $employment->end->greaterThan(Carbon::now());
        })->sortByDesc('start');

        $employments_old = $employe->employments->filter(function ($employment){
            return $employment->end != null and $employment->end->lessThan(Carbon::now());
        })->sortByDesc('end');


        return view('personal.employes.show', [
            'employe' => $employe,
            'departments' => Group::all(),
            'hour_types' => HourType::all(),
            'employments' => $employments,
            'employments_old' => $employments_old
        ]);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $employe
     * @return RedirectResponse
     */
    public function update(CreateEmployeRequest $request, User $employe)
    {

        $settings = $employe->employe_data;
        if (is_null($settings)){
            $settings = new EmployeData($request->validated());
            $settings->user_id = $employe->id;
            $settings->save();
        } else {
            $settings->update($request->validated() );
        }

        if (($settings->caldav_working_time == 1 or $settings->caldav_events == 1) and $settings->caldav_uuid == null){
            $settings->update([
                'caldav_uuid' => Str::uuid()
            ]);
        }

        if (($settings->caldav_working_time == 0 and $settings->caldav_events == 0) and $settings->caldav_uuid != null){
            $settings->update([
                'caldav_uuid' => null
            ]);
        }


        return redirect()->back()->with([
            'type' => "success",
            'Meldung' => 'Daten aktualisiert.'
        ]);
    }
    public function updateData(UpdateEmployeDataRequest $request, User $employe)
    {

        if ($request->holidayClaim !=  $employe->getHolidayClaim()){
            $claim = new EmployeHolidayClaim([
                'holiday_claim' => $request->holidayClaim,
                'employe_id' => $employe->id,
                'date_start' => $request->date_start,
                'changedBy' => auth()->id()
            ]);
            $claim->save();
        }

        if ($request->time_recording_key != null){
            $employe->employe_data()->update([
                'time_recording_key' => $request->time_recording_key
            ]);
        }

        if ($request->secret_key != null){
            $employe->employe_data()->update([
                'secret_key' => $request->secret_key
            ]);
        }

        if ($request->mail_timesheet != null){
            $employe->employe_data()->update([
                'mail_timesheet' => $request->mail_timesheet
            ]);
        }

        return redirect()->back()->with([
            'type' => "success",
            'Meldung' => 'Daten aktualisiert.'
        ]);
    }



    public function addSalary(CreateEmployeSalaryRequest $request, User $employe){

        if (!is_null($employe->salary) and $employe->salary->start->greaterThan(Carbon::createFromFormat('Y-m-d',$request->start))){
            return redirectBack('danger', 'Das Datum muss nach dem derzeit genutzten Datum liegen');
        }

        $employe->salaryGroups()->create($request->validated());



        return redirectBack('success', 'Einstufung wurde festgelegt');
    }

    public function ical($employe, $uuid){
        $employe = User::findOrFail($employe);
        if (isset($uuid) and $employe?->settings?->caldav_uuid == $uuid){
            $icalObject = "BEGIN:VCALENDAR
               VERSION:2.0
               METHOD:PUBLISH
               PRODID:-//" . config('app.name') . "//Termine//DE\n
               ";

            if ($employe?->settings?->caldav_events == 1){
                $events = $employe->roster_events()->whereDate('date', '>=', Carbon::now()->startOfDay())->get();
                foreach ($events as $event){
                    $icalObject.=$event->getICal();
                }
            }

            if ($employe?->settings?->caldav_working_time == 1){
                $working_times = $employe->working_times()->whereDate('date', '>=', Carbon::now()->startOfDay())->get();
                foreach ($working_times as $working_time){
                    $icalObject.=$working_time->getICal();
                }
            }


            // close calendar
            $icalObject .= "END:VCALENDAR";

            // Set the headers
            header('Content-type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . config('app.name') . '_'.$employe->familienname. '.ics"');

            $icalObject = str_replace(' ', '', $icalObject);
            $icalObject = str_replace('__', ' ', $icalObject);

            return $icalObject;
        }

        abort(404);
    }

    public function show_self(){
        return view('personal.employes.selfEdit', [
            'employe' => auth()->user(),
        ]);
    }

    public function update_self(selfUpdateProfileRequest $request){

        $user = auth()->user();
        $data = $user->employe_data;
        if (is_null($data)){
            $data = new EmployeData($request->validated());
            $data->user_id = $user->id;
            $data->save();
        } else {
            $data->update($request->validated() );
        }

        $user->update([
            'name' => $request->vorname . ' ' . $request->familienname
        ]);

        return redirect()->back()->with([
            'type' => 'success',
            'message' => 'Daten aktualisiert'
        ]);
    }

    public function photo(Request $request){
        $user = auth()->user();
        $user->clearMediaCollection('profile');
        $user->addMedia($request->file('file'))->toMediaCollection('profile');

        return redirect()->back()->with([
            'type' => 'success',
            'message' => 'Foto aktualisiert'
        ]);
    }
}
