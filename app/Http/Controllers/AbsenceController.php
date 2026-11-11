<?php

namespace App\Http\Controllers;

use App\Exports\AbsenceExport;
use App\Http\Requests\CreateAbsenceRequest;
use App\Models\Absence;
use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
}
