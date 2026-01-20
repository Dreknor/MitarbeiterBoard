<?php

namespace App\Http\Controllers;

use App\Exports\AbsenceExport;
use App\Exports\SickNotesCompleteExport;
use App\Exports\SickNotesByUserExport;
use App\Exports\SickNotesExport;
use App\Exports\SickNotesUserSummaryExport;
use App\Http\Requests\CreateAbsenceRequest;
use App\Mail\DailyAbsenceReport;
use App\Mail\NewAbsenceMail;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

/**
 *
 */
class AbsenceController extends Controller
{
    /**
     * @return Application|Factory|View|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     */
    public function index() {
        if (!auth()->user()->can('view old absences')){

            Log::debug(
                'Abwesenheit: Berechtigung fehlt',
                [
                    'user' => auth()->user(),
                    'route' => 'absences.index'
                ]
            );

            return redirect(url('/'))->with([
                'type'  => "warning",
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $absences = Absence::query()->orderByDesc('start')->with('user')->get();

        return view('absences.index',[
            'absences' => $absences
        ]);
    }

    /**
     * @param CreateAbsenceRequest $request
     * @return RedirectResponse
     */
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

            Log::debug(
                'Abwesenheit: Abwesenheit gespeichert',
                [
                    'user' => auth()->user(),
                    'absence' => $absence,
                    'route' => 'absences.store'
                ]
            );


        } else {
            $absence->update([
                'end' => $request->end,
                'sick_note_required' => $request->sick_note_required,
            ]);

            Log::debug(
                'Abwesenheit: Abwesenheit aktualisiert',
                [
                    'user' => auth()->user(),
                    'absence' => $absence,
                    'route' => 'absences.store'
                ]
            );
        }
        $users = User::where('absence_abo_now', 1)->get();
        foreach ($users as $user){
            if ($user->send_mails_if_absence == true or (!$user->hasAbsence(now()) and !$user->hasHoliday(now()))){
                $mail = Mail::to($user)->queue(new NewAbsenceMail($absence->user->name,$absence->start->format('d.m.Y'),$absence->end->format('d.m.Y'),$absence->reason));
            }
        }

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Abwesenheit wurde gespeichert.'
        ]);
    }

    /**
     * @param $type
     * @return RedirectResponse
     */
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

    /**
     * @return void
     */
    public function dailyReport(){

        Log::debug(
            'Abwesenheit: täglicher Report',
            [

            ]
        );

        $absences = Absence::where('start', '<=', Carbon::now()->format('Y-m-d'))
                            ->where('end', '>=', Carbon::now()->format('Y-m-d'))
                            ->get();

        $users = User::where('absence_abo_daily', 1)->get();

        Log::debug(
            'Abwesenheit: täglicher Report',
            [
                'absences' => $absences,
                'users' => $users,
            ]
        );

        foreach ($users as $user){
            if ($user->send_mails_if_absence == true or (!$user->hasAbsence(now()) and !$user->hasHoliday(now()))) {
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
    }

    /**
     * @param Absence $absence
     * @return RedirectResponse
     */
    public function delete(Absence $absence){
        if ((auth()->user()->can('delete absences') or auth()->id() == $absence->creator_id)){
            if ($absence->end->greaterThan(Carbon::today()->startOfDay())){
                Log::debug(
                    'Abwesenheit: Abwesenheit gelöscht',
                    [
                        'user' => auth()->user(),
                        'absence' => $absence,
                        'route' => 'absences.delete'
                    ]
                );
                $absence->delete();

                return redirect()->back()->with([
                    'type' => 'info',
                    'Meldung' => 'Abwesenheitsmitteilung gelöscht'
                ]);


            } else {
                Log::debug(
                    'Abwesenheit: Abwesenheit löschen fehlgeschlagen',
                    [
                        'user' => auth()->user(),
                        'absence' => $absence,
                        'Meldung' => 'Abwesenheitsmitteilung kann nicht gelöscht werden, da das Ende in der Zukunft liegen muss.'
                    ]
                );
                return redirect()->back()->with([
                    'type' => 'danger',
                    'Meldung' => 'Abwesenheitsmitteilung kann nicht gelöscht werden, da das Ende in der Zukunft liegen muss.'
                ]);
            }

        }

        return redirect()->back()->with([
            'type' => 'danger',
            'Meldung' => 'Berechtigung fehlt'
        ]);
    }

    /**
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export (){
        if (!auth()->user()->can('export absence')){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        return Excel::download(new AbsenceExport(), 'Abwesenheiten.xlsx');

    }

    /**
     * @return Application|Factory|View|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     */
    public function sick_notes_index(Request $request) {
        if (!auth()->user()->can('manage sick_notes')){
            return redirect(url('/'))->with([
                'type'  => "warning",
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        // Filter-Parameter aus Request holen
        $filterReason = $request->get('reason');
        $filterUser = $request->get('user');
        $filterSickNoteStatus = $request->get('sick_note_status');
        $sortBy = $request->get('sort_by', 'start');
        $sortOrder = $request->get('sort_order', 'desc');

        // Mitarbeiter-Übersicht Filter
        $filterWithNoteMin = $request->get('filter_with_note_min');
        $filterWithoutNoteMin = $request->get('filter_without_note_min');
        $filterMissingNoteMin = $request->get('filter_missing_note_min');
        $userSortBy = $request->get('user_sort_by', 'user');
        $userSortOrder = $request->get('user_sort_order', 'asc');

        // Query aufbauen
        $query = Absence::where(function ($query){
            $query->whereIn('reason', config('absences.absence_sick_note'))
                ->orWhere('sick_note_required', 1);
        })->whereDate('start', '>=', Carbon::now()->subYear());

        // Filter anwenden
        if ($filterReason) {
            $query->where('reason', $filterReason);
        }

        if ($filterUser) {
            $query->where('users_id', $filterUser);
        }

        // Filter nach Krankenschein-Status (vereinfacht - ohne 'days' Prüfung in Query)
        if ($filterSickNoteStatus) {
            switch ($filterSickNoteStatus) {
                case 'with_note':
                    $query->whereNotNull('sick_note_date');
                    break;
                case 'without_note':
                    // Nur Einträge ohne Schein und ohne Pflicht
                    // Die Tage-Prüfung erfolgt auf Collection-Ebene
                    $query->whereNull('sick_note_date')
                        ->where('sick_note_required', false);
                    break;
                case 'missing_note':
                    // Nur Einträge ohne Schein aber mit Pflicht
                    $query->whereNull('sick_note_date')
                        ->where('sick_note_required', true);
                    break;
            }
        }

        // Sortierung anwenden (nur für Datenbankfelder)
        if (in_array($sortBy, ['start', 'end', 'reason'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $absences = $query->with('user')->get();

        // WICHTIG: Zusätzliche Filterung nach 'days' auf Collection-Ebene
        if ($filterSickNoteStatus === 'without_note') {
            $sickNoteDaysThreshold = settings('absence_sick_note_days', 'absences') ?? config('absences.absence_sick_note_days');
            $absences = $absences->filter(function($absence) use ($sickNoteDaysThreshold) {
                return $absence->days < $sickNoteDaysThreshold;
            });
        } elseif ($filterSickNoteStatus === 'missing_note') {
            $sickNoteDaysThreshold = settings('absence_sick_note_days', 'absences') ?? config('absences.absence_sick_note_days');
            $absences = $absences->filter(function($absence) use ($sickNoteDaysThreshold) {
                return $absence->days >= $sickNoteDaysThreshold || $absence->sick_note_required;
            });
        }

        // Sortierung nach 'days' (berechnetes Feld) auf Collection-Ebene
        if ($sortBy === 'days') {
            $absences = $sortOrder === 'asc'
                ? $absences->sortBy('days')
                : $absences->sortByDesc('days');
        }

        // Mitarbeiter-Übersicht berechnen
        $users_absences = $absences->groupBy('users_id');
        $users = new Collection();

        foreach ($users_absences as $absences_user){
            $without_note = 0;
            $with_note = 0;
            $missing_note = 0;

            foreach ($absences_user as $absence){
                if ($absence->days < settings('absence_sick_note_days', 'absences') and $absence->sick_note_required == false)
                {
                    $without_note+=$absence->days;
                }
                if (($absence->days >= settings('absence_sick_note_days', 'absences') or $absence->sick_note_required != false) and is_null($absence->sick_note_date))
                {
                    $missing_note+=$absence->days;
                }
                if (($absence->days >= settings('absence_sick_note_days', 'absences') or $absence->sick_note_required != false) and !is_null($absence->sick_note_date))
                {
                    $with_note+=$absence->days;
                }
            }


            $users->add([
                'user' => $absence->user->name,
                'user_id' => $absence->user->id,
                'without_note' => $without_note,
                'with_note' => $with_note,
                'missing_note' => $missing_note,
            ]);

        }

        // Filter für Mitarbeiter-Übersicht anwenden
        $filteredUsers = $users->filter(function($user) use ($filterWithNoteMin, $filterWithoutNoteMin, $filterMissingNoteMin) {
            $pass = true;

            if (!is_null($filterWithNoteMin) && $filterWithNoteMin !== '') {
                $pass = $pass && ($user['with_note'] >= intval($filterWithNoteMin));
            }

            if (!is_null($filterWithoutNoteMin) && $filterWithoutNoteMin !== '') {
                $pass = $pass && ($user['without_note'] >= intval($filterWithoutNoteMin));
            }

            if (!is_null($filterMissingNoteMin) && $filterMissingNoteMin !== '') {
                $pass = $pass && ($user['missing_note'] >= intval($filterMissingNoteMin));
            }

            return $pass;
        });

        // Sortierung für Mitarbeiter-Übersicht anwenden
        if ($userSortOrder === 'desc') {
            $sortedUsers = $filteredUsers->sortByDesc($userSortBy);
        } else {
            $sortedUsers = $filteredUsers->sortBy($userSortBy);
        }

        // Alle Abwesenheitsgründe für Filter-Dropdown
        $allReasons = Absence::where(function ($query){
                $query->whereIn('reason', config('absences.absence_sick_note'))
                    ->orWhere('sick_note_required', 1);
            })
            ->whereDate('start', '>=', Carbon::now()->subYear())
            ->distinct()
            ->pluck('reason')
            ->sort();

        // Alle Mitarbeiter für Filter-Dropdown
        $allUsers = User::whereHas('absences', function($query) {
            $query->where(function ($q){
                $q->whereIn('reason', config('absences.absence_sick_note'))
                    ->orWhere('sick_note_required', 1);
            })->whereDate('start', '>=', Carbon::now()->subYear());
        })->orderBy('name')->get();

        return view('absences.sicknotes',[
           'absences' => $absences,
            'users' => $sortedUsers,
            'allReasons' => $allReasons,
            'allUsers' => $allUsers,
            'filterReason' => $filterReason,
            'filterUser' => $filterUser,
            'filterSickNoteStatus' => $filterSickNoteStatus,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }

    /**
     * @param Absence $absence
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     */
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

        Log::info(
            'Abwesenheit: Krankenschein aktualisiert',
            [
                'user' => auth()->user(),
                'absence' => $absence,
            ]
        );

        return redirect()->back()->with([
            'type'  => "success",
            'Meldung' => 'Krankenschein erfasst für '.$absence->user->name.' ('.$absence->start->format('d.m.Y').' - '.$absence->end->format('d.m.Y').')'
        ]);
    }

    /**
     * @param Absence $absence
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     */
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

        Log::info(
            'Abwesenheit: Krankenschein entfernt',
            [
                'user' => auth()->user(),
                'absence' => $absence,
            ]
        );

        return redirect()->back()->with([
            'type'  => "success",
            'Meldung' => 'Krankenschein entfernt für '.$absence->user->name.' ('.$absence->start->format('d.m.Y').' - '.$absence->end->format('d.m.Y').')'
        ]);
    }

    /**
     * Export aller Krankmeldungen
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
     */
    public function sick_notes_export(Request $request) {
        if (!auth()->user()->can('manage sick_notes')){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        // Gleiche Filter wie in sick_notes_index anwenden
        $filterReason = $request->get('reason');
        $filterUser = $request->get('user');
        $filterSickNoteStatus = $request->get('sick_note_status');

        $query = Absence::where(function ($query){
            $query->whereIn('reason', config('absences.absence_sick_note'))
                ->orWhere('sick_note_required', 1);
        })->whereDate('start', '>=', Carbon::now()->subYear());

        if ($filterReason) {
            $query->where('reason', $filterReason);
        }

        if ($filterUser) {
            $query->where('users_id', $filterUser);
        }

        // Filter nach Krankenschein-Status (vereinfacht - ohne 'days' Prüfung in Query)
        if ($filterSickNoteStatus) {
            switch ($filterSickNoteStatus) {
                case 'with_note':
                    $query->whereNotNull('sick_note_date');
                    break;
                case 'without_note':
                    // Nur Einträge ohne Schein und ohne Pflicht
                    // Die Tage-Prüfung erfolgt auf Collection-Ebene
                    $query->whereNull('sick_note_date')
                        ->where('sick_note_required', false);
                    break;
                case 'missing_note':
                    // Nur Einträge ohne Schein aber mit Pflicht
                    $query->whereNull('sick_note_date')
                        ->where('sick_note_required', true);
                    break;
            }
        }

        $absences = $query->with('user')->orderByDesc('start')->get();

        // WICHTIG: Zusätzliche Filterung nach 'days' auf Collection-Ebene
        if ($filterSickNoteStatus === 'without_note') {
            $sickNoteDaysThreshold = settings('absence_sick_note_days', 'absences') ?? config('absences.absence_sick_note_days');
            $absences = $absences->filter(function($absence) use ($sickNoteDaysThreshold) {
                return $absence->days < $sickNoteDaysThreshold;
            });
        } elseif ($filterSickNoteStatus === 'missing_note') {
            $sickNoteDaysThreshold = settings('absence_sick_note_days', 'absences') ?? config('absences.absence_sick_note_days');
            $absences = $absences->filter(function($absence) use ($sickNoteDaysThreshold) {
                return $absence->days >= $sickNoteDaysThreshold || $absence->sick_note_required;
            });
        }

        // Mitarbeiter-Übersicht berechnen
        $users_absences = $absences->groupBy('users_id');
        $users = new Collection();

        foreach ($users_absences as $absences_user){
            $without_note = 0;
            $with_note = 0;
            $missing_note = 0;

            foreach ($absences_user as $absence){
                $sickNoteDaysThreshold = settings('absence_sick_note_days', 'absences') ?? config('absences.absence_sick_note_days');

                if ($absence->days < $sickNoteDaysThreshold and $absence->sick_note_required == false)
                {
                    $without_note+=$absence->days;
                }
                if (($absence->days >= $sickNoteDaysThreshold or $absence->sick_note_required != false) and is_null($absence->sick_note_date))
                {
                    $missing_note+=$absence->days;
                }
                if (($absence->days >= $sickNoteDaysThreshold or $absence->sick_note_required != false) and !is_null($absence->sick_note_date))
                {
                    $with_note+=$absence->days;
                }
            }

            $users->add([
                'user' => $absence->user->name,
                'user_id' => $absence->user->id,
                'without_note' => $without_note,
                'with_note' => $with_note,
                'missing_note' => $missing_note,
            ]);
        }

        $filename = 'Krankmeldungen_' . Carbon::now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new SickNotesCompleteExport($absences, $users->sortBy('user')),
            $filename
        );
    }

    /**
     * Export von Krankmeldungen eines einzelnen Mitarbeiters
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
     */
    public function sick_notes_export_user(User $user) {
        if (!auth()->user()->can('manage sick_notes')){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $absences = Absence::where('users_id', $user->id)
            ->where(function ($query){
                $query->whereIn('reason', config('absences.absence_sick_note'))
                    ->orWhere('sick_note_required', 1);
            })
            ->whereDate('start', '>=', Carbon::now()->subYear())
            ->orderByDesc('start')
            ->get();

        $filename = 'Krankmeldungen_' . $user->name . '_' . Carbon::now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new SickNotesByUserExport($absences, $user->name),
            $filename
        );
    }
}

