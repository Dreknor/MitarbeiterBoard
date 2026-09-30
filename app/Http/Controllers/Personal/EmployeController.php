<?php

namespace App\Http\Controllers\Personal;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateEmployeRequest;
use App\Http\Requests\personal\selfUpdateProfileRequest;
use App\Http\Requests\UpdateEmployeDataRequest;
use App\Http\Requests\personal\BulkUpdateHolidayClaimRequest;
use App\Models\Group;
use App\Models\personal\EmployeData;
use App\Models\personal\EmployeHolidayClaim;
use App\Models\User;
use App\Services\Personal\Zeit\UrlaubskontoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EmployeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        $employes = User::query()
            ->with([
                'employe_data:id,user_id,familienname,vorname',
                'employments' => fn ($q) => $q->with('department:id,name')->orderBy('start'),
            ])
            ->get()
            ->map(function (User $user) {
                $offen   = $user->employments->filter(fn ($e) => $e->status !== EmploymentStatus::Beendet);
                $laufend = $offen->filter(fn ($e) => $e->status === EmploymentStatus::Aktiv
                    && $e->start->lessThanOrEqualTo(today())
                    && ($e->end === null || $e->end->greaterThanOrEqualTo(today())));
                $naechstesEnde = $offen->pluck('end')->filter()->sort()->first();

                $status = match (true) {
                    $laufend->isNotEmpty()                                            => 'aktiv',
                    $offen->contains(fn ($e) => $e->status === EmploymentStatus::Ruhend) => 'ruhend',
                    $offen->isNotEmpty()                                              => 'kuenftig',
                    $user->employments->isNotEmpty()                                  => 'ausgeschieden',
                    default                                                           => 'ohne',
                };

                return [
                    'id'           => $user->id,
                    'familienname' => $user->familienname,
                    'vorname'      => $user->vorname,
                    'email'        => $user->email,
                    'bereiche'     => $offen->map(fn ($e) => $e->department?->name)->filter()->unique()->values()->implode(', '),
                    'prozent'      => round($laufend->sum(fn ($e) => $e->percent), 1),
                    'status'       => $status,
                    'ende'         => $naechstesEnde?->format('d.m.Y'),
                    'endeBald'     => $naechstesEnde !== null && $naechstesEnde->lessThanOrEqualTo(today()->addDays(90)),
                ];
            })
            ->sortBy(fn ($e) => mb_strtolower($e['familienname'] . ' ' . $e['vorname']))
            ->values();

        return view('personal.employes.index', [
            'employes' => $employes,
            'counts'   => $employes->countBy('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $employe
     * @return View
     */
    public function show(User $employe)
    {
        // /employes/{id} und /personal/mitarbeiter/{id} sind dieselbe Stelle:
        // Einstieg ist die Personalakte, die Stammdaten-Bearbeitung ist eine Unterseite davon.
        if (auth()->user()->can('view personal_data')) {
            return redirect()->route('personal.personalakte.show', $employe->id);
        }

        return redirect()->route('personal.personalakte.stammdaten', $employe->id);
    }

    /**
     * Stammdaten bearbeiten (Unterseite der Personalakte).
     */
    public function stammdaten(User $employe)
    {
        // Ohne gespeicherte Stammdaten wird das Formular mit Vorschlägen aus dem Benutzernamen gefüllt;
        // angelegt wird der Datensatz erst beim Speichern (kein Schreibzugriff beim bloßen Ansehen).
        $data = $employe->employe_data ?? new EmployeData([
            'familienname'         => Str::contains($employe->name, ' ') ? Str::afterLast($employe->name, ' ') : $employe->name,
            'vorname'              => Str::contains($employe->name, ' ') ? Str::beforeLast($employe->name, ' ') : '',
            'staatsangehoerigkeit' => 'deutsch',
        ]);

        $holidayRest = app(UrlaubskontoService::class)->rest($employe, now()->year);

        return view('personal.employes.show', [
            'employe'      => $employe,
            'data'         => $data,
            'holidayClaim' => $employe->getHolidayClaim(),
            'holidayRest'  => UrlaubskontoService::format($holidayRest),
            'workingTimeAccount' => $employe->timesheet_latest?->working_time_account,
            'firstStart'   => $employe->employments()->min('start'),
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
        $validated = $request->validated();
        // Leeres PIN-Feld bedeutet "unverändert lassen"
        if (empty($validated['secret_key'] ?? null)) {
            unset($validated['secret_key']);
        }
        if (is_null($settings)){
            $settings = new EmployeData($validated);
            $settings->user_id = $employe->id;
            $settings->save();
        } else {
            $settings->update($validated);
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

        if ($request->filled('send_mail_if_absence')) {
            $employe->update([
                'send_mails_if_absence' => (bool) $request->send_mail_if_absence
            ]);
        }

        return redirect()->back()->with([
            'type' => "success",
            'Meldung' => 'Daten aktualisiert.'
        ]);
    }

    public function updateData(UpdateEmployeDataRequest $request, User $employe)
    {
        if ((int) $request->holidayClaim !== (int) $employe->getHolidayClaim()) {
            EmployeHolidayClaim::create([
                'holiday_claim' => $request->holidayClaim,
                'employe_id' => $employe->id,
                'date_start' => $request->date_start,
                'changedBy' => auth()->id()
            ]);
        }

        // Nur übermittelte Felder ändern. filled() statt "!= null": sonst würde "0" (= nein) nie gespeichert.
        $fields = collect(['time_recording_key', 'secret_key', 'mail_timesheet', 'google_calendar_link', 'caldav_working_time', 'caldav_events'])
            ->filter(fn ($field) => $request->filled($field))
            ->mapWithKeys(fn ($field) => [$field => $request->input($field)])
            ->all();

        if ($fields !== []) {
            // Über das Model speichern, damit die PIN gehasht und die Änderung protokolliert wird
            $data = $employe->employe_data ?? new EmployeData(['user_id' => $employe->id]);
            $data->fill($fields);
            $data->user_id = $employe->id;
            $data->save();
        }

        if ($request->filled('send_mails_if_absence')) {
            $employe->update([
                'send_mails_if_absence' => (bool) $request->send_mails_if_absence
            ]);
        }

        // Atom-Feed URL speichern (leerer String = zurück zum Standard)
        if ($request->has('atom_feed_url')) {
            $employe->update([
                'atom_feed_url' => $request->atom_feed_url ?: null
            ]);
        }

        return redirect()->back()->with([
            'type' => "success",
            'Meldung' => 'Daten aktualisiert.'
        ]);
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
            'name' => $request->vorname . ' ' . $request->familienname,
            'send_mails_if_absence' => $request->send_mail_if_absence
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

        \Cache::forget('user_photo_'.$user->id);

        return redirect()->back()->with([
            'type' => 'success',
            'message' => 'Foto aktualisiert'
        ]);
    }

    /**
     * Show the form for bulk updating holiday claims by group.
     *
     * @return View
     */
    public function bulkHolidayClaimForm()
    {
        if (!auth()->user()->can('edit employe')) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $groups = Group::withCount('users')->orderBy('name')->get();

        return view('personal.employes.bulk-holiday-claim', [
            'groups' => $groups
        ]);
    }

    /**
     * Update holiday claims for all employees in a group.
     *
     * @param BulkUpdateHolidayClaimRequest $request
     * @return RedirectResponse
     */
    public function bulkUpdateHolidayClaim(BulkUpdateHolidayClaimRequest $request)
    {
        $group = Group::findOrFail($request->group_id);

        // Hole alle Mitarbeiter der Gruppe
        $employees = $group->users;

        if ($employees->isEmpty()) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Die ausgewählte Gruppe hat keine Mitarbeiter.'
            ]);
        }

        $updatedCount = 0;

        foreach ($employees as $employee) {
            // Nur aktualisieren, wenn sich der Urlaubsanspruch geändert hat
            if ($request->holiday_claim != $employee->getHolidayClaim()) {
                EmployeHolidayClaim::create([
                    'holiday_claim' => $request->holiday_claim,
                    'employe_id' => $employee->id,
                    'date_start' => $request->date_start,
                    'changedBy' => auth()->id()
                ]);
                $updatedCount++;
            }
        }

        if ($updatedCount == 0) {
            return redirect()->back()->with([
                'type' => 'info',
                'Meldung' => 'Kein Mitarbeiter wurde aktualisiert, da alle bereits den gleichen Urlaubsanspruch haben.'
            ]);
        }

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => "Urlaubsanspruch für {$updatedCount} Mitarbeiter der Gruppe '{$group->name}' wurde erfolgreich aktualisiert."
        ]);
    }
}
