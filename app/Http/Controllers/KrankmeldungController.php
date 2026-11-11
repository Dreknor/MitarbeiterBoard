<?php

namespace App\Http\Controllers;

use App\Exports\SickNotesByUserExport;
use App\Exports\SickNotesCompleteExport;
use App\Models\Absence;
use App\Models\User;
use App\Services\KrankmeldungService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Krankmeldungen (/sick_notes, Recht "manage sick_notes"): Krankenscheine erfassen,
 * Auswertungen und Excel-Export. Alle Regeln im KrankmeldungService.
 */
class KrankmeldungController extends Controller
{
    public function __construct(private readonly KrankmeldungService $service)
    {
    }

    public function index(Request $request): View
    {
        $filter = $this->service->filter($this->validiereFilter($request));
        $meldungen = $this->service->meldungen($filter);
        $liste = $this->service->liste($meldungen, $filter);

        $seite = LengthAwarePaginator::resolveCurrentPage();
        $proSeite = 50;
        $paginiert = new LengthAwarePaginator(
            $liste->forPage($seite, $proSeite)->values(),
            $liste->count(),
            $proSeite,
            $seite,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('absences.sicknotes', [
            'filter' => $filter,
            'meldungen' => $paginiert,
            'kennzahlen' => $this->service->kennzahlen($meldungen, $filter),
            'verlauf' => $this->service->monatsverlauf($meldungen, $filter),
            'wochentage' => $this->service->wochentage($meldungen),
            'dauer' => $this->service->dauerverteilung($meldungen),
            'personen' => $this->service->personen($meldungen, $filter),
            'gruende' => $this->service->basis()->distinct()->orderBy('reason')->pluck('reason'),
            'personenAuswahl' => User::query()
                ->whereIn('id', $this->service->basis()->select('users_id'))
                ->orderBy('name')->get(['id', 'name']),
            'service' => $this->service,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filter = $this->service->filter($this->validiereFilter($request));
        $meldungen = $this->service->meldungen($filter);

        return Excel::download(
            new SickNotesCompleteExport($this->service, $filter, $meldungen),
            'Krankmeldungen_'.$filter['von']->format('Y-m-d').'_bis_'.$filter['bis']->format('Y-m-d').'.xlsx'
        );
    }

    public function exportPerson(Request $request, User $user): BinaryFileResponse
    {
        $filter = $this->service->filter(array_merge($this->validiereFilter($request), ['person' => $user->id, 'grund' => null, 'status' => null]));
        $meldungen = $this->service->liste($this->service->meldungen($filter), $filter);

        return Excel::download(
            new SickNotesByUserExport($this->service, $meldungen, $user->name),
            'Krankmeldungen_'.KrankmeldungService::sichererName($user->name, 60).'_'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Krankenschein erfassen (Eingangsdatum, Standard heute).
     */
    public function scheinErfassen(Request $request, Absence $absence): RedirectResponse
    {
        $this->pruefeKrankmeldung($absence);
        $daten = $request->validate([
            'datum' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $absence->update(['sick_note_date' => $daten['datum'] ?? Carbon::today()]);
        Log::info('Krankmeldung: Krankenschein erfasst', ['user' => $request->user()->id, 'absence' => $absence->id]);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Krankenschein erfasst für '.$absence->user->name.' ('.$this->zeitraum($absence).')',
        ]);
    }

    public function scheinEntfernen(Request $request, Absence $absence): RedirectResponse
    {
        $this->pruefeKrankmeldung($absence);

        $absence->update(['sick_note_date' => null]);
        Log::info('Krankmeldung: Krankenschein entfernt', ['user' => $request->user()->id, 'absence' => $absence->id]);

        return redirect()->back()->with([
            'type' => 'info',
            'Meldung' => 'Krankenschein entfernt für '.$absence->user->name.' ('.$this->zeitraum($absence).')',
        ]);
    }

    /**
     * Schein ab dem ersten Tag verlangen bzw. die Pflicht zurücknehmen (Krankheiten ab N Arbeitstagen
     * behalten die Pflicht – die setzt das Model automatisch).
     */
    public function pflicht(Request $request, Absence $absence): RedirectResponse
    {
        $this->pruefeKrankmeldung($absence);
        $daten = $request->validate(['pflicht' => ['required', 'boolean']]);

        $absence->update(['sick_note_required' => (bool) $daten['pflicht']]);
        Log::info('Krankmeldung: Krankenschein-Pflicht geändert', ['user' => $request->user()->id, 'absence' => $absence->id, 'pflicht' => $absence->sick_note_required]);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => ($absence->sick_note_required ? 'Krankenschein wird verlangt' : 'Krankenschein nicht mehr verlangt')
                .' – '.$absence->user->name.' ('.$this->zeitraum($absence).')',
        ]);
    }

    /** @return array<string, mixed> */
    private function validiereFilter(Request $request): array
    {
        return $request->validate([
            'zeitraum' => ['nullable', 'in:'.implode(',', array_keys(KrankmeldungService::ZEITRAEUME))],
            'von' => ['nullable', 'date', 'required_if:zeitraum,frei'],
            'bis' => ['nullable', 'date', 'after_or_equal:von'],
            'grund' => ['nullable', 'string', 'max:64'],
            'person' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(KrankmeldungService::STATUS))],
            'sort' => ['nullable', 'in:'.implode(',', KrankmeldungService::SORTIERUNG)],
            'richtung' => ['nullable', 'in:asc,desc'],
            'psort' => ['nullable', 'in:'.implode(',', KrankmeldungService::PERSONEN_SORTIERUNG)],
            'prichtung' => ['nullable', 'in:asc,desc'],
        ]);
    }

    private function pruefeKrankmeldung(Absence $absence): void
    {
        abort_unless($this->service->gehoertDazu($absence), 404);
    }

    private function zeitraum(Absence $absence): string
    {
        return $absence->start->format('d.m.Y').' – '.$absence->end->format('d.m.Y');
    }
}
