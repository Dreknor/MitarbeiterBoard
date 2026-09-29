<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createHolidayRequest;
use App\Models\Group;
use App\Models\personal\Holiday;
use App\Models\personal\HolidayAccountEntry;
use App\Models\User;
use App\Services\Personal\Zeit\HolidayService;
use App\Services\Personal\Zeit\UrlaubskontoService;
use App\Services\Personal\Zeit\ZeitZugriff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Urlaubsverwaltung: Antrag, Genehmigung, Stornierung, Teamkalender und Urlaubskonto.
 * Schreibende Logik liegt im HolidayService, Rechte in der HolidayPolicy.
 */
class HolidayController extends Controller
{
    public function __construct(
        private readonly HolidayService $holidays,
        private readonly UrlaubskontoService $konto,
        private readonly ZeitZugriff $zugriff,
    ) {
    }

    public function index(Request $request, $month = null, $year = null)
    {
        $actor = $request->user();
        abort_unless($actor->can('has holidays') || $actor->can('approve holidays'), 403);

        $monat = $this->monat($month, $year);
        $monatsEnde = $monat->copy()->endOfMonth();

        $kalenderNutzer = $this->kalenderNutzer($actor, $monat, $monatsEnde);
        $kalenderUrlaube = Holiday::query()
            ->nichtAbgelehnt()
            ->whereIn('employe_id', $kalenderNutzer->pluck('id'))
            ->ueberschneidet($monat->toDateString(), $monatsEnde->toDateString())
            ->get()
            ->groupBy('employe_id');

        $tage = [];
        for ($tag = $monat->copy(); $tag->lte($monatsEnde); $tag->addDay()) {
            $ferien = is_ferien($tag);
            $tage[] = [
                'date' => $tag->copy(),
                'frei' => $tag->isWeekend() || (bool) is_holiday($tag),
                'feiertag' => is_holiday($tag)['title'] ?? null,
                'ferien' => $ferien ? (is_array($ferien) ? ($ferien['name'] ?? 'Ferien') : ($ferien->name ?? 'Ferien')) : null,
            ];
        }

        $eigeneAntraege = $actor->holidays()
            ->withTrashed()
            ->whereYear('start_date', $monat->year)
            ->orderBy('start_date')
            ->get();

        return view('personal.holidays.index', [
            'monat' => $monat,
            'tage' => $tage,
            'kalenderNutzer' => $kalenderNutzer,
            'kalenderUrlaube' => $kalenderUrlaube,
            'gruppen' => $actor->groups_rel()->orderBy('name')->get(['groups.id', 'groups.name']),
            'konto' => $actor->can('has holidays') ? $this->konto->uebersicht($actor, $monat->year) : null,
            'eigeneAntraege' => $eigeneAntraege,
            'antragFuer' => $this->antragsNutzer($actor),
            'darfFuerAlle' => $actor->can('createForAll', Holiday::class),
            'zuEntscheiden' => $this->zuEntscheiden($actor),
        ]);
    }

    /**
     * Live-Vorschau im Antragsformular (Tage, Rest, Überschneidungen im Team).
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employe_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
        ]);

        $employe = User::findOrFail($data['employe_id']);
        $this->authorize('createFor', [Holiday::class, $employe]);

        $start = Carbon::parse($data['start_date']);
        $ende = Carbon::parse($data['end_date']);

        if ($start->diffInDays($ende) > 400) {
            return response()->json(['message' => 'Zeitraum zu lang.'], 422);
        }

        return response()->json($this->holidays->vorschau($employe, $start, $ende, (bool) ($data['half_day'] ?? false)));
    }

    public function store(createHolidayRequest $request)
    {
        $actor = $request->user();
        $start = Carbon::parse($request->start_date);
        $ende = Carbon::parse($request->end_date);
        $halberTag = $request->boolean('half_day');

        if ($request->employe_id === 'all') {
            $this->authorize('createForAll', Holiday::class);

            $gruppe = $request->filled('group_id') ? Group::find($request->group_id) : null;
            $mitarbeitende = User::permission('has holidays')
                ->when($gruppe, fn ($q) => $q->whereHas('groups_rel', fn ($g) => $g->where('groups.id', $gruppe->id)))
                ->get();

            $anzahl = $this->holidays->fuerMehrereEintragen($actor, $mitarbeitende, $start, $ende, $request->comment);

            return redirect()->route('holidays.index', [$start->month, $start->year])
                ->with(['type' => 'success', 'Meldung' => $anzahl.' Urlaubseinträge angelegt'.($gruppe ? ' (Gruppe '.$gruppe->name.')' : '').'.']);
        }

        $employe = User::findOrFail($request->employe_id);
        $this->authorize('createFor', [Holiday::class, $employe]);

        $antraege = $this->holidays->beantragen($actor, $employe, $start, $ende, $halberTag, $request->comment);
        $genehmigt = $antraege->every(fn (Holiday $h) => $h->approved);

        return redirect()->route('holidays.index', [$start->month, $start->year])->with([
            'type' => 'success',
            'Meldung' => $genehmigt ? 'Urlaub wurde eingetragen und genehmigt.' : 'Urlaub wurde beantragt. Die zuständige Person wird benachrichtigt.',
        ]);
    }

    public function approve(Request $request, Holiday $holiday)
    {
        $this->authorize('approve', $holiday);
        $this->holidays->genehmigen($holiday, $request->user());

        return redirectBack('success', 'Urlaub von '.$holiday->employe->name.' genehmigt.');
    }

    public function reject(Request $request, Holiday $holiday)
    {
        $this->authorize('reject', $holiday);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->holidays->ablehnen($holiday, $request->user(), $data['reason'] ?? null);

        return redirectBack('success', 'Urlaub von '.$holiday->employe->name.' abgelehnt.');
    }

    public function requestCancellation(Request $request, Holiday $holiday)
    {
        $this->authorize('requestCancellation', $holiday);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->holidays->stornoBeantragen($holiday, $request->user(), $data['reason'] ?? null);

        return redirectBack('success', 'Stornierung beantragt.');
    }

    public function decideCancellation(Request $request, Holiday $holiday)
    {
        $this->authorize('decideCancellation', $holiday);
        $data = $request->validate(['decision' => ['required', 'in:approve,deny']]);
        $this->holidays->stornoEntscheiden($holiday, $request->user(), $data['decision'] === 'approve');

        return redirectBack('success', $data['decision'] === 'approve' ? 'Urlaub storniert.' : 'Stornierung abgelehnt – der Urlaub bleibt bestehen.');
    }

    public function destroy(Request $request, Holiday $holiday)
    {
        $this->authorize('delete', $holiday);
        $name = $holiday->employe?->name ?? 'Unbekannt';
        $this->holidays->stornieren($holiday, $request->user());

        return redirectBack('success', 'Urlaub von '.$name.' ab '.$holiday->start_date->format('d.m.Y').' wurde entfernt.');
    }

    /**
     * Verwaltung: Anträge filtern und Urlaubskonten aller zuständigen Mitarbeitenden.
     */
    public function manage(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->can('approve holidays'), 403);

        $jahr = (int) $request->input('year', now()->year);
        $mitarbeitende = $this->verwalteteNutzer($actor);
        $status = $request->input('status', 'alle');

        $antraege = Holiday::query()
            ->with(['employe', 'approved_by_user'])
            ->whereIn('employe_id', $mitarbeitende->pluck('id'))
            ->ueberschneidet(Carbon::create($jahr, 1, 1)->toDateString(), Carbon::create($jahr, 12, 31)->toDateString())
            ->when($request->filled('user_id'), fn ($q) => $q->where('employe_id', $request->integer('user_id')))
            ->when($status === 'offen', fn ($q) => $q->offen())
            ->when($status === 'genehmigt', fn ($q) => $q->genehmigt())
            ->when($status === 'abgelehnt', fn ($q) => $q->where('rejected', true))
            ->when($status === 'storno', fn ($q) => $q->whereNotNull('cancellation_requested_at'))
            ->when($request->boolean('future_only'), fn ($q) => $q->whereDate('end_date', '>=', today()))
            ->orderByDesc('start_date')
            ->paginate(50)
            ->withQueryString();

        $konten = $request->input('tab') === 'konten'
            ? $mitarbeitende->map(fn (User $u) => ['user' => $u] + $this->konto->uebersicht($u, $jahr))
            : collect();

        return view('personal.holidays.manage', [
            'jahr' => $jahr,
            'antraege' => $antraege,
            'mitarbeitende' => $mitarbeitende,
            'konten' => $konten,
            'tab' => $request->input('tab', 'antraege'),
            'filter' => [
                'user_id' => $request->input('user_id', ''),
                'status' => $status,
                'future_only' => $request->boolean('future_only'),
            ],
        ]);
    }

    /**
     * Urlaubskonto einer Person (eigenes oder – mit Recht – fremdes) inkl. Buchungen.
     */
    public function account(Request $request, User $employe, ?int $year = null)
    {
        $actor = $request->user();
        abort_unless($actor->id === $employe->id || $this->zugriff->darfUrlaubGenehmigen($actor, $employe) || $actor->can('manageAccount', [Holiday::class, $employe]), 403);

        $jahr = $year ?? now()->year;

        return view('personal.holidays.account', [
            'employe' => $employe,
            'jahr' => $jahr,
            'konto' => $this->konto->uebersicht($employe, $jahr),
            'antraege' => $employe->holidays()->withTrashed()->with('approved_by_user')
                ->ueberschneidet(Carbon::create($jahr, 1, 1)->toDateString(), Carbon::create($jahr, 12, 31)->toDateString())
                ->orderBy('start_date')->get(),
            'buchungen' => HolidayAccountEntry::with('creator')->where('employe_id', $employe->id)->where('year', $jahr)->latest()->get(),
            'darfBuchen' => $actor->can('manageAccount', [Holiday::class, $employe]),
        ]);
    }

    public function storeAccountEntry(Request $request, User $employe)
    {
        $this->authorize('manageAccount', [Holiday::class, $employe]);

        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'days' => ['required', 'numeric', 'between:-100,100', 'not_in:0'],
            'type' => ['required', 'in:'.implode(',', array_keys(HolidayAccountEntry::TYPES))],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        HolidayAccountEntry::create($data + ['employe_id' => $employe->id, 'created_by' => $request->user()->id]);
        $this->konto->vergessen($employe);

        return redirectBack('success', 'Buchung gespeichert.');
    }

    public function destroyAccountEntry(Request $request, HolidayAccountEntry $entry)
    {
        $this->authorize('manageAccount', [Holiday::class, $entry->employe]);
        $entry->delete();

        return redirectBack('success', 'Buchung gelöscht.');
    }

    public function export(Request $request, $year = null, $group = null)
    {
        abort_unless($request->user()->can('approve holidays'), 403);

        $jahr = $year ? (int) $year : now()->year;
        $start = Carbon::create($jahr, 1, 1);
        $ende = Carbon::create($jahr, 12, 31);

        $mitarbeitende = $this->verwalteteNutzer($request->user())
            ->when($group, fn (Collection $c) => $c->filter(fn (User $u) => $u->groups_rel->contains(fn ($g) => (string) $g->id === (string) $group || $g->name === $group)));

        $urlaube = Holiday::query()
            ->nichtAbgelehnt()
            ->with(['employe', 'employe.groups_rel'])
            ->whereIn('employe_id', $mitarbeitende->pluck('id'))
            ->ueberschneidet($start->toDateString(), $ende->toDateString())
            ->get();

        $pdf = \PDF::loadView('personal.holidays.export', [
            'holidays' => $urlaube,
            'monthStart' => $start,
            'users' => $mitarbeitende->sortBy('name'),
        ])
            ->setOption('orientation', 'landscape')
            ->setOption('margin-bottom', 10)
            ->setOption('margin-top', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf->download('urlaub_'.$jahr.'.pdf');
    }

    // =========================================================================

    private function monat($month, $year): Carbon
    {
        if ($month === null || $year === null || !ctype_digit((string) $month) || !ctype_digit((string) $year)) {
            return Carbon::now()->startOfMonth();
        }

        $month = max(1, min(12, (int) $month));
        $year = max(2000, min(2100, (int) $year));

        return Carbon::create($year, $month, 1)->startOfDay();
    }

    /**
     * Wer erscheint im Teamkalender?
     */
    private function kalenderNutzer(User $actor, Carbon $von, Carbon $bis): Collection
    {
        $basis = User::permission('has holidays')->with(['groups_rel', 'employments'])->orderBy('name');

        if ($actor->can('approve all holidays') || $actor->can('edit employe')) {
            $nutzer = $basis->get();
        } elseif ((string) settings('show_holidays', 'holidays') === '1') {
            $gruppen = $actor->groups_rel->pluck('id');
            $nutzer = $basis->whereHas('groups_rel', fn ($q) => $q->whereIn('groups.id', $gruppen))->get();
        } else {
            $nutzer = $basis->where('id', $actor->id)->get()
                ->merge($this->unterstellteMitUrlaub($actor)->load(['groups_rel', 'employments']));
        }

        // Nur Personen mit Vertrag im Monat (oder ganz ohne hinterlegten Vertrag)
        return $nutzer->unique('id')->filter(function (User $u) use ($von, $bis) {
            return $u->employments->isEmpty()
                || $u->employments->contains(fn ($e) => $e->start->lte($bis) && ($e->end === null || $e->end->gte($von)));
        })->values();
    }

    /**
     * Für wen darf der Benutzer Urlaub eintragen?
     */
    private function antragsNutzer(User $actor): Collection
    {
        $nutzer = collect($actor->can('has holidays') ? [$actor] : []);

        if ($actor->can('approve all holidays') && $actor->can('approve holidays')) {
            return $nutzer->merge(User::permission('has holidays')->where('id', '!=', $actor->id)->orderBy('name')->get())->unique('id')->values();
        }

        return $nutzer->merge($this->unterstellteMitUrlaub($actor))->unique('id')->values();
    }

    private function unterstellteMitUrlaub(User $actor): Collection
    {
        $ids = $this->zugriff->unterstellteIds($actor);

        return $ids === [] ? collect() : User::permission('has holidays')->whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Mitarbeitende, deren Urlaub der Benutzer verwaltet.
     */
    private function verwalteteNutzer(User $actor): Collection
    {
        if ($actor->can('approve all holidays') || $actor->can('edit employe')) {
            return User::permission('has holidays')->with('groups_rel')->orderBy('name')->get();
        }

        return $this->unterstellteMitUrlaub($actor)->load('groups_rel');
    }

    /**
     * Offene Anträge und Stornierungswünsche, über die der Benutzer entscheiden darf.
     */
    private function zuEntscheiden(User $actor): Collection
    {
        if (!$actor->can('approve holidays')) {
            return collect();
        }

        return Holiday::query()
            ->with('employe')
            ->where('employe_id', '!=', $actor->id)
            ->where(fn ($q) => $q->where(fn ($o) => $o->offen())->orWhereNotNull('cancellation_requested_at'))
            ->where('rejected', false)
            ->orderBy('start_date')
            ->get()
            ->filter(fn (Holiday $h) => $h->employe !== null && $this->zugriff->darfUrlaubGenehmigen($actor, $h->employe))
            ->values();
    }
}
