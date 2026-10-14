<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\StoreTimesheetDayRequest;
use App\Http\Requests\API\v1\UpdateTimesheetDayRequest;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use App\Services\Personal\TimeValidationService;
use App\Services\Personal\Zeit\TimeRecordingService;
use App\Services\Personal\Zeit\TimesheetService;
use App\Services\Personal\Zeit\ZeitZugriff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1 – Arbeitszeitnachweise.
 *
 * Zugriff wie im Web (TimesheetPolicy/ZeitZugriff): Mitarbeitende sehen und bearbeiten ihre eigenen
 * Nachweise; Vorgesetzte bzw. Stellvertretungen mit "lock timesheets" die Nachweise der ihnen
 * unterstellten Personen, "edit employe" (Personal) alle. Fremde Nachweise sind nie über die eigene ID
 * erreichbar – jede Route prüft die Policy am konkreten Nachweis bzw. an der Person.
 *
 * Logik: TimesheetService (gemeinsam mit dem Web-Frontend). Zeiten in Minuten.
 */
class TimesheetApiController extends Controller
{
    public function __construct(
        private readonly TimesheetService $timesheets,
        private readonly ZeitZugriff $zugriff,
        private readonly TimeValidationService $validationService,
        private readonly TimeRecordingService $recording,
    ) {
    }

    // ── Personen & Übersicht ─────────────────────────────────────────────

    /**
     * GET /api/v1/timesheets/employees – Personen, deren Nachweise der Benutzer sehen darf (inkl. sich selbst).
     */
    public function employees(Request $request): JsonResponse
    {
        $actor = $request->user();
        $vormonat = Carbon::today()->startOfMonth()->subMonth();

        $mitarbeitende = User::query()
            ->whereHas('employments')
            ->when(!$actor->can('edit employe'), fn ($q) => $q->whereIn('id', $this->zugriff->unterstellteIds($actor)))
            ->where('id', '!=', $actor->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $u) => $u->can('has timesheet') && $this->zugriff->verwaltetNachweiseVon($actor, $u));

        if ($actor->can('has timesheet')) {
            $mitarbeitende->prepend($actor);
        }

        $nachweise = Timesheet::query()
            ->whereIn('employe_id', $mitarbeitende->pluck('id'))
            ->where('year', $vormonat->year)->where('month', $vormonat->month)
            ->get()->keyBy('employe_id');

        return response()->json([
            'data' => $mitarbeitende->map(fn (User $u) => $this->personDaten($u) + [
                'is_self' => $u->id === $actor->id,
                'previous_month' => ($ts = $nachweise->get($u->id)) ? $this->kurzDaten($ts) : null,
            ])->values(),
        ]);
    }

    /**
     * GET /api/v1/timesheets/employees/{employe} – alle Monatsnachweise einer Person.
     */
    public function overview(User $employe): JsonResponse
    {
        $this->authorize('viewEmploye', [Timesheet::class, $employe]);

        return response()->json([
            'data' => [
                'employee' => $this->personDaten($employe),
                'timesheets' => $employe->timesheets()->with('lockedBy')->orderByDesc('year')->orderByDesc('month')->get()
                    ->map(fn (Timesheet $ts) => $this->kurzDaten($ts))->values(),
            ],
        ]);
    }

    /**
     * GET /api/v1/timesheets/employees/{employe}/months/{month} – Monatsnachweis (YYYY-MM) mit Tageszeilen.
     * Legt den Nachweis wie im Web bei Bedarf an und bringt ihn auf den aktuellen Stand.
     */
    public function month(Request $request, User $employe, string $month): JsonResponse
    {
        $this->authorize('viewEmploye', [Timesheet::class, $employe]);

        $monat = $this->monat($month);
        if ($monat->gt(Carbon::today()->startOfMonth()->addMonth())) {
            throw ValidationException::withMessages(['month' => 'Nachweise sind höchstens für den nächsten Monat abrufbar.']);
        }
        if (!$employe->employments()->exists() && !$employe->timesheets()->exists()) {
            abort(404, 'Für '.$employe->name.' ist keine Anstellung eingetragen.');
        }

        $timesheet = $this->timesheets->forMonth($employe, $monat);
        $this->timesheets->fuerAnzeigeAktualisieren($timesheet, $request->user()->can('edit', $timesheet));

        return response()->json(['data' => $this->monatsDaten($request->user(), $timesheet)]);
    }

    /**
     * GET /api/v1/timesheets/{timesheet} – vorhandenen Nachweis nur lesen: legt nichts an, übernimmt
     * keinen Dienstplan und rechnet nicht neu (gespeicherter Stand, z. B. für Prüfungen).
     */
    public function show(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('view', $timesheet);

        return response()->json(['data' => $this->monatsDaten($request->user(), $timesheet)]);
    }

    /**
     * GET /api/v1/timesheets/{timesheet}/pdf – Nachweis als PDF (wie der Web-Export).
     */
    public function pdf(Timesheet $timesheet): Response
    {
        $this->authorize('view', $timesheet);

        if ($timesheet->monthStart()->gt(Carbon::today())) {
            throw ValidationException::withMessages(['month' => 'Dieser Monat liegt in der Zukunft.']);
        }

        return $this->timesheets->pdf($timesheet)->download($this->timesheets->pdfDateiname($timesheet));
    }

    // ── Kommen/Gehen (eigene Zeiterfassung) ──────────────────────────────

    /**
     * GET /api/v1/timesheets/clock – bin ich gerade eingecheckt? (für die Standort-Erinnerung der App)
     */
    public function clockStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('has timesheet'), 403, 'Für dich wird keine Arbeitszeit erfasst.');

        return response()->json(['data' => $this->stempelStatus($user)]);
    }

    /**
     * POST /api/v1/timesheets/clock – einchecken ("in") bzw. auschecken ("out") mit der aktuellen Uhrzeit.
     * Anders als das Terminal schaltet die API nicht blind um: wer schon eingecheckt ist, kann nicht
     * erneut einchecken (422) – so bucht eine doppelt angetippte Benachrichtigung nichts Falsches.
     */
    public function clock(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('has timesheet'), 403, 'Für dich wird keine Arbeitszeit erfasst.');
        $daten = $request->validate(['action' => ['required', 'in:in,out']]);

        $timesheet = $this->timesheets->forMonth($user, Carbon::now());
        $this->authorize('edit', $timesheet);

        $eingecheckt = $this->recording->istEingestempelt($user);
        if ($daten['action'] === 'in' && $eingecheckt) {
            throw ValidationException::withMessages(['action' => 'Du bist bereits eingecheckt.']);
        }
        if ($daten['action'] === 'out' && !$eingecheckt) {
            throw ValidationException::withMessages(['action' => 'Du bist heute nicht eingecheckt.']);
        }

        [$zeile] = $this->recording->stempeln($user, null, 'Dienstplan-App');

        return response()->json([
            'data' => $this->stempelStatus($user),
            'entry' => $this->eintragDaten($zeile->fresh()),
        ]);
    }

    private function stempelStatus(User $user): array
    {
        $offen = $this->recording->offeneBuchung($user);
        $vortag = $this->recording->offenVomVortag($user);

        return [
            'checked_in' => $offen !== null,
            'since' => $offen?->start?->format('H:i'),
            'open_from_yesterday' => $vortag?->date?->toDateString(),
        ];
    }

    // ── Buchungen ────────────────────────────────────────────────────────

    /**
     * POST /api/v1/timesheets/{timesheet}/days – Arbeitszeit buchen.
     */
    public function storeDay(StoreTimesheetDayRequest $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('edit', $timesheet);
        $tag = $this->tag($timesheet, $request->input('date'));

        $zeile = $this->timesheets->buchen($timesheet, $tag, $request->validated());
        $this->timesheets->fehlendesGehenErledigt($timesheet, $tag);

        return $this->antwort($request, $timesheet, 201, ['entry' => $this->eintragDaten($zeile->fresh())]);
    }

    /**
     * POST /api/v1/timesheets/{timesheet}/credits – Gutschrift (z. B. Fortbildung) laut config.abwesenheiten_arbeitszeit.
     */
    public function storeCredit(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('edit', $timesheet);
        $daten = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'absence' => ['required', 'string', 'max:60'],
        ]);
        $tag = $this->tag($timesheet, $daten['date']);

        $zeile = $this->timesheets->gutschreiben($timesheet, $tag, $daten['absence']);
        $this->timesheets->fehlendesGehenErledigt($timesheet, $tag);

        return $this->antwort($request, $timesheet, 201, ['entry' => $this->eintragDaten($zeile->fresh())]);
    }

    /**
     * POST /api/v1/timesheets/{timesheet}/apply-roster – Dienstplanzeiten übernehmen
     * (mit "date" für einen Tag, sonst alle offenen Tage des Monats).
     */
    public function applyRoster(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('edit', $timesheet);
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        if ($request->filled('date')) {
            $tag = $this->tag($timesheet, $request->input('date'));
            if ($this->timesheets->planUebernehmen($timesheet, $tag) === null) {
                throw ValidationException::withMessages(['date' => 'Für diesen Tag liegen keine Dienstplanzeiten vor.']);
            }
            $this->timesheets->fehlendesGehenErledigt($timesheet, $tag);
            $anzahl = 1;
        } else {
            $anzahl = $this->timesheets->planUebernehmenMonat($timesheet);
        }

        return $this->antwort($request, $timesheet, 200, ['applied_days' => $anzahl]);
    }

    /**
     * PUT /api/v1/timesheets/days/{timesheetDay} – Zeitbuchung ändern.
     */
    public function updateDay(UpdateTimesheetDayRequest $request, TimesheetDays $timesheetDay): JsonResponse
    {
        $timesheet = $timesheetDay->timesheet;
        abort_if($timesheet === null, 404);
        $this->authorize('edit', $timesheet);

        if ($timesheetDay->is_credit) {
            throw ValidationException::withMessages(['day' => 'Gutschriften können nicht geändert, nur gelöscht werden.']);
        }

        $this->timesheets->aendern($timesheetDay, $request->validated());

        return $this->antwort($request, $timesheet, 200, ['entry' => $this->eintragDaten($timesheetDay->fresh())]);
    }

    /**
     * DELETE /api/v1/timesheets/days/{timesheetDay} – Buchung löschen (Urlaub/Abwesenheiten nicht).
     */
    public function destroyDay(Request $request, TimesheetDays $timesheetDay): JsonResponse
    {
        $timesheet = $timesheetDay->timesheet;
        abort_if($timesheet === null, 404);
        $this->authorize('edit', $timesheet);

        $tag = $timesheetDay->date->copy();
        $this->timesheets->loeschen($timesheetDay);
        $this->timesheets->fehlendesGehenErledigt($timesheet, $tag);

        return $this->antwort($request, $timesheet);
    }

    // ── Neuberechnung ────────────────────────────────────────────────────

    /**
     * POST /api/v1/timesheets/{timesheet}/recalculate – Monat ausdrücklich neu berechnen (auch Altmonate)
     * und prüfen; offene Folgemonate werden mitgerechnet (wie "Neu berechnen" im Web).
     */
    public function recalculate(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('edit', $timesheet);

        $this->timesheets->syncMonat($timesheet);
        $this->timesheets->recalculate($timesheet, true, true);
        $this->validationService->runForEmployee($timesheet->employe, $timesheet->monthStart(), $request->user(), false);

        return $this->antwort($request, $timesheet);
    }

    /**
     * POST /api/v1/timesheets/employees/{employe}/recalculate – alle offenen Monate bis zum laufenden der Reihe
     * nach neu berechnen (Stundenkonto). Nur Vorgesetzte/Stellvertretungen bzw. Personal, nie für sich selbst.
     */
    public function recalculateAll(Request $request, User $employe): JsonResponse
    {
        abort_unless($this->zugriff->verwaltetNachweiseVon($request->user(), $employe), 403);

        $anzahl = 0;
        foreach ($employe->timesheets()->whereNull('locked_at')->orderBy('year')->orderBy('month')->get() as $ts) {
            if ($ts->monthStart()->gt(now()->endOfMonth())) {
                break;
            }
            $this->timesheets->recalculate($ts, false, true);
            $anzahl++;
        }

        return response()->json([
            'data' => [
                'employee' => $this->personDaten($employe),
                'recalculated' => $anzahl,
                'timesheets' => $employe->timesheets()->with('lockedBy')->orderByDesc('year')->orderByDesc('month')->get()
                    ->map(fn (Timesheet $ts) => $this->kurzDaten($ts))->values(),
            ],
        ]);
    }

    // ── Workflow ─────────────────────────────────────────────────────────

    /** POST /api/v1/timesheets/{timesheet}/submit – Monat einreichen (nur die Person selbst, nach Monatsende). */
    public function submit(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('submit', $timesheet);
        $this->timesheets->einreichen($timesheet, $request->user());

        return $this->antwort($request, $timesheet);
    }

    /** POST /api/v1/timesheets/{timesheet}/lock – bestätigen und abschließen (Vorgesetzte/Personal). */
    public function lock(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('lock', $timesheet);
        $this->timesheets->abschliessen($timesheet, $request->user());

        return $this->antwort($request, $timesheet);
    }

    /** POST /api/v1/timesheets/{timesheet}/return – eingereichten Nachweis zur Korrektur zurückgeben. */
    public function returnToEmploye(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('returnToEmploye', $timesheet);
        $daten = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->timesheets->zurueckgeben($timesheet, $request->user(), $daten['reason']);

        return $this->antwort($request, $timesheet);
    }

    /** POST /api/v1/timesheets/{timesheet}/unlock – Sperre aufheben (nur "edit employe"). */
    public function unlock(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('unlock', $timesheet);
        $this->timesheets->entsperren($timesheet);

        return $this->antwort($request, $timesheet);
    }

    // =========================================================================

    private function antwort(Request $request, Timesheet $timesheet, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(['data' => $this->monatsDaten($request->user(), $timesheet->refresh())] + $extra, $status);
    }

    private function monatsDaten(User $actor, Timesheet $timesheet): array
    {
        $zeilen = $this->timesheets->monatsZeilen($timesheet);
        $gezaehlt = $zeilen->where('zaehlt', true);
        $saldoVorher = (int) ($this->timesheets->vorgaenger($timesheet)?->working_time_account ?? 0);

        return $this->kurzDaten($timesheet) + [
            'employee' => $this->personDaten($timesheet->employe),
            'submitted_at' => $timesheet->submitted_at?->toIso8601String(),
            'return_reason' => $timesheet->return_reason,
            'previous_balance_minutes' => $this->minuten($saldoVorher),
            'month_balance_minutes' => $this->minuten($timesheet->working_time_account - $saldoVorher),
            'target_minutes' => $this->minuten($gezaehlt->sum('soll')),
            'actual_minutes' => $this->minuten($gezaehlt->sum('ist')),
            'holidays' => [
                'old' => $timesheet->holidays_old,
                'new' => $timesheet->holidays_new,
                'rest' => $timesheet->holidays_rest,
            ],
            'frozen' => $this->timesheets->istEingefroren($timesheet) && !$timesheet->is_locked,
            'legacy_model' => $this->timesheets->istHistorisch($timesheet),
            'missing_clock_outs' => $this->timesheets->fehlendesGehen($timesheet->employe, $timesheet->monthStart())
                ->map(fn ($a) => Carbon::parse($a->date)->toDateString())->unique()->values(),
            'permissions' => [
                'edit' => $actor->can('edit', $timesheet),
                'submit' => $actor->can('submit', $timesheet),
                'lock' => $actor->can('lock', $timesheet),
                'return' => $actor->can('returnToEmploye', $timesheet),
                'unlock' => $actor->can('unlock', $timesheet),
            ],
            'credit_reasons' => collect(config('config.abwesenheiten_arbeitszeit', []))
                ->map(fn ($prozent, $grund) => ['name' => $grund, 'percent' => (float) $prozent])->values(),
            'days' => $zeilen->map(fn (array $z) => [
                'date' => $z['date']->toDateString(),
                'holiday' => $z['feiertag'],
                'workday' => $z['arbeitstag'],
                'counts' => $z['zaehlt'],
                'target_minutes' => $this->minuten($z['soll']),
                'actual_minutes' => $this->minuten($z['ist']),
                'diff_minutes' => $this->minuten($z['diff']),
                'open' => $z['offen'],
                'plan' => $z['plan'],
                'entries' => $z['entries']->map(fn (TimesheetDays $d) => $this->eintragDaten($d))->values(),
            ])->values(),
        ];
    }

    private function kurzDaten(Timesheet $timesheet): array
    {
        return [
            'id' => $timesheet->id,
            'employee_id' => $timesheet->employe_id,
            'year' => (int) $timesheet->year,
            'month' => (int) $timesheet->month,
            'status' => $timesheet->status,
            'status_label' => $timesheet->status_label,
            'balance_minutes' => $this->minuten($timesheet->working_time_account),
            'submitted' => $timesheet->submitted_at !== null,
            'locked_at' => $timesheet->locked_at?->toIso8601String(),
            'locked_by' => $timesheet->lockedBy ? $this->personDaten($timesheet->lockedBy) : null,
        ];
    }

    private function eintragDaten(TimesheetDays $d): array
    {
        return [
            'id' => $d->id,
            'date' => $d->date->toDateString(),
            'start' => $d->start?->format('H:i'),
            'end' => $d->end?->format('H:i'),
            'pause' => $d->pause !== null ? (int) $d->pause : null,
            'comment' => $d->comment,
            'is_credit' => $d->is_credit,
            'credit_percent' => $d->is_credit ? (float) $d->percent_of_workingtime : null,
            'source' => $d->source,
            'is_automatic' => $d->is_automatic,
            'duration_minutes' => $this->minuten($d->duration),
        ];
    }

    private function personDaten(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name];
    }

    private function minuten(float|int $sekunden): int
    {
        return (int) round($sekunden / 60);
    }

    private function monat(string $month): Carbon
    {
        [$jahr, $monat] = array_map('intval', explode('-', $month));
        abort_if($monat < 1 || $monat > 12, 404);

        return Carbon::create($jahr, $monat, 1)->startOfDay();
    }

    private function tag(Timesheet $timesheet, string $date): Carbon
    {
        $tag = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();

        if ($tag->year !== (int) $timesheet->year || $tag->month !== (int) $timesheet->month) {
            throw ValidationException::withMessages(['date' => 'Das Datum gehört nicht zu diesem Nachweis.']);
        }

        return $tag;
    }
}
