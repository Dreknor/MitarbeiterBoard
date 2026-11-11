<?php

namespace App\Http\Controllers;

use App\Exports\AbsenceExport;
use App\Exports\ShortSickNotesWithoutCertificateExport;
use App\Exports\SickNotesCompleteExport;
use App\Exports\SickNotesByUserExport;
use App\Exports\SickNotesExport;
use App\Exports\SickNotesUserSummaryExport;
use App\Http\Requests\CreateAbsenceRequest;
use App\Models\Absence;
use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 *
 */
class AbsenceController extends Controller
{
    /** Zeitraum-Filter der Übersicht */
    public const ZEITRAEUME = [
        'aktuell' => 'Aktuell & geplant',
        'heute' => 'Heute',
        'geplant' => 'Geplant',
        'vergangen' => 'Vergangen',
        'alle' => 'Alle',
    ];

    /**
     * Übersicht aller Abwesenheiten – serverseitig gefiltert und seitenweise
     * (vorher wurden alle Abwesenheiten aller Jahre geladen und im Browser gefiltert).
     */
    public function index(Request $request, AbsenceService $service): View
    {
        $user = $request->user();
        $filter = $this->filter($request);

        $absences = $this->gefiltert($filter)
            ->with(['user', 'creator'])
            ->paginate(25)
            ->withQueryString();

        $heute = Carbon::today();
        $aktuelle = Absence::query()->whereDate('end', '>=', $heute)->get(['id', 'users_id', 'start', 'end']);

        return view('absences.index', [
            'absences' => $absences,
            'filter' => $filter,
            'zeitraeume' => self::ZEITRAEUME,
            'gruende' => Absence::query()->distinct()->orderBy('reason')->pluck('reason'),
            'personen' => User::query()->whereHas('absences')->orderBy('name')->get(['id', 'name']),
            'kennzahlen' => [
                'heute' => $aktuelle->filter(fn (Absence $a) => $a->start->lte($heute))->unique('users_id')->count(),
                'woche' => $aktuelle->filter(fn (Absence $a) => $a->start->lte($heute->copy()->addDays(7)))->unique('users_id')->count(),
                'geplant' => $aktuelle->filter(fn (Absence $a) => $a->start->gt($heute))->count(),
            ],
            'meldbarePersonen' => $service->darfFuerAndereMelden($user) ? $service->meldbarePersonen($user) : collect(),
            'darfKrankenschein' => $user->can('create absences'),
            'zeigeKrankenschein' => $user->can('manage sick_notes'),
            'service' => $service,
        ]);
    }

    /**
     * @param CreateAbsenceRequest $request
     * @return RedirectResponse
     */
    public function store(CreateAbsenceRequest $request, AbsenceService $absences){
        $absence = $absences->melden($request->user(), $request->validated());

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Abwesenheit für '.$absence->user->name.' wurde gespeichert.'
        ]);
    }

    /**
     * Löschen nur per DELETE (vorher GET-Link ohne CSRF-Schutz).
     */
    public function destroy(Absence $absence, AbsenceService $absences): RedirectResponse
    {
        if (!$absences->darfLoeschen(auth()->user(), $absence)){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        if ($absence->holiday_id !== null) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Diese Abwesenheit stammt aus einem Urlaub – bitte den Urlaub stornieren.'
            ]);
        }

        if (!$absences->loeschbar($absence)){
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Abwesenheitsmitteilung kann nicht gelöscht werden, da das Ende in der Zukunft liegen muss.'
            ]);
        }

        $absences->loeschen(auth()->user(), $absence);

        return redirect()->back()->with([
            'type' => 'info',
            'Meldung' => 'Abwesenheitsmitteilung gelöscht'
        ]);
    }

    /**
     * Excel-Export – übernimmt die Filter der Übersicht.
     */
    public function export(Request $request){
        $filter = $this->filter($request);
        $absences = $this->gefiltert($filter)->with(['user', 'creator'])->get();

        return Excel::download(new AbsenceExport($absences), 'Abwesenheiten_'.Carbon::now()->format('Y-m-d').'.xlsx');
    }

    /**
     * @return array{q: string, zeitraum: string, grund: string, person: int|null, von: string|null, bis: string|null}
     */
    private function filter(Request $request): array
    {
        $daten = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'zeitraum' => ['nullable', 'in:'.implode(',', array_keys(self::ZEITRAEUME))],
            'grund' => ['nullable', 'string', 'max:64'],
            'person' => ['nullable', 'integer'],
            'von' => ['nullable', 'date'],
            'bis' => ['nullable', 'date'],
        ]);

        return [
            'q' => trim((string) ($daten['q'] ?? '')),
            'zeitraum' => $daten['zeitraum'] ?? 'aktuell',
            'grund' => (string) ($daten['grund'] ?? ''),
            'person' => isset($daten['person']) ? (int) $daten['person'] : null,
            'von' => $daten['von'] ?? null,
            'bis' => $daten['bis'] ?? null,
        ];
    }

    private function gefiltert(array $filter): Builder
    {
        $heute = Carbon::today();

        $query = Absence::query();

        match ($filter['zeitraum']) {
            'aktuell' => $query->whereDate('end', '>=', $heute)->orderBy('start')->orderBy('id'),
            'heute' => $query->whereDate('start', '<=', $heute)->whereDate('end', '>=', $heute)->orderBy('start'),
            'geplant' => $query->whereDate('start', '>', $heute)->orderBy('start')->orderBy('id'),
            'vergangen' => $query->whereDate('end', '<', $heute)->orderByDesc('start')->orderByDesc('id'),
            default => $query->orderByDesc('start')->orderByDesc('id'),
        };

        if ($filter['von']) {
            $query->whereDate('end', '>=', $filter['von']);
        }
        if ($filter['bis']) {
            $query->whereDate('start', '<=', $filter['bis']);
        }
        if ($filter['grund'] !== '') {
            $query->where('reason', $filter['grund']);
        }
        if ($filter['person']) {
            $query->where('users_id', $filter['person']);
        }
        if ($filter['q'] !== '') {
            $suche = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filter['q']).'%';
            $query->where(fn (Builder $q) => $q
                ->where('reason', 'like', $suche)
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $suche)));
        }

        return $query;
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

        // Berechnung für kurze Krankmeldungen ohne Schein (1-2 Tage, nur "krank", ohne Schein)
        $shortSickNotesQuery = Absence::whereDate('start', '>=', Carbon::now()->subYear())
            ->where('reason', 'krank')  // Nur "krank", nicht "Kind krank"
            ->whereNull('sick_note_date')  // Ohne Krankenschein
            ->where('sick_note_required', false)  // Krankenschein nicht erforderlich
            ->with('user')
            ->get();

        // Filterung nach Dauer (1-2 Tage) auf Collection-Ebene, da 'days' ein berechnetes Attribut ist
        $shortSickNotesFiltered = $shortSickNotesQuery->filter(function($absence) {
            return $absence->days >= 1 && $absence->days <= 2;
        });

        // Gruppierung nach Mitarbeiter
        $shortSickNotesGrouped = $shortSickNotesFiltered->groupBy('users_id');
        $shortSickNotesData = new Collection();

        foreach ($shortSickNotesGrouped as $userId => $userAbsences) {
            $totalDays = $userAbsences->sum('days');
            $count = $userAbsences->count();
            $userName = $userAbsences->first()->user->name;

            $shortSickNotesData->add([
                'user_id' => $userId,
                'user' => $userName,
                'count' => $count,
                'total_days' => $totalDays,
            ]);
        }

        // Sortierung nach Summe der Tage (absteigend)
        $shortSickNotesData = $shortSickNotesData->sortByDesc('total_days')->values();

        $filename = 'Krankmeldungen_' . Carbon::now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new SickNotesCompleteExport($absences, $users->sortBy('user'), $shortSickNotesData),
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

