<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\personal\Holiday;
use App\Models\User;
use App\Services\Personal\Zeit\HolidayService;
use App\Services\Personal\Zeit\UrlaubskontoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API v1 – Urlaub (Dienstplan-App).
 *
 * Wie die Web-Urlaubsverwaltung: eigenes Urlaubskonto und eigene Anträge, Anträge für Unterstellte
 * (bzw. mit "approve all holidays" für alle) sowie Entscheidungen über Anträge und Stornierungen.
 * Rechte: HolidayPolicy/ZeitZugriff am konkreten Antrag bzw. an der Person. Logik: HolidayService,
 * Konto: UrlaubskontoService.
 */
class HolidayApiController extends Controller
{
    public function __construct(
        private readonly HolidayService $holidays,
        private readonly UrlaubskontoService $konto,
    ) {
    }

    /**
     * GET /api/v1/holidays?year=2026 – eigenes Konto, eigene Anträge des Jahres, Personen für neue Anträge.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $jahr = $this->jahr($request);

        $antraege = $actor->can('has holidays')
            ? $actor->holidays()->withTrashed()->with(['employe', 'approved_by_user'])
                ->ueberschneidet(Carbon::create($jahr, 1, 1)->toDateString(), Carbon::create($jahr, 12, 31)->toDateString())
                ->orderBy('start_date')->get()
            : collect();

        return response()->json([
            'data' => [
                'year' => $jahr,
                'account' => $actor->can('has holidays') ? $this->kontoDaten($this->konto->uebersicht($actor, $jahr)) : null,
                'requests' => $antraege->map(fn (Holiday $h) => $this->antragDaten($actor, $h))->values(),
                'employees' => $this->holidays->antragsNutzer($actor)
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'is_self' => $u->id === $actor->id])->values(),
                'pending_decisions' => $this->holidays->zuEntscheiden($actor)->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/holidays/decisions – offene Anträge und Stornierungswünsche, über die der Benutzer entscheidet.
     */
    public function decisions(Request $request): JsonResponse
    {
        $actor = $request->user();

        return response()->json([
            'data' => $this->holidays->zuEntscheiden($actor)->map(fn (Holiday $h) => $this->antragDaten($actor, $h) + [
                'rest_after' => round($this->konto->rest($h->employe, $h->start_date->year)
                    - $this->konto->beantragt($h->employe, $h->start_date->year), 1),
            ])->values(),
        ]);
    }

    /**
     * GET /api/v1/holidays/preview – Tage, Rest danach und Überschneidungen im Team (wie im Antragsformular).
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
            throw ValidationException::withMessages(['end_date' => 'Zeitraum zu lang.']);
        }

        $v = $this->holidays->vorschau($employe, $start, $ende, (bool) ($data['half_day'] ?? false));

        return response()->json([
            'data' => [
                'days' => $v['tage'],
                'year' => $v['jahr'],
                'rest_before' => $v['rest_vorher'],
                'rest_after' => $v['rest_nachher'],
                'own_overlap' => $v['ueberschneidung_eigen'],
                'team' => collect($v['team'])->map(fn (array $t) => [
                    'name' => $t['name'],
                    'from' => $t['von'],
                    'to' => $t['bis'],
                    'status' => $t['status'],
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /api/v1/holidays – Urlaub beantragen (für Genehmigende direkt genehmigt). Jahresübergreifende
     * Anträge werden wie im Web je Jahr geteilt.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employe_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:255'],
        ], [
            'end_date.after_or_equal' => 'Das Enddatum darf nicht vor dem Startdatum liegen.',
        ]);

        $actor = $request->user();
        $employe = User::findOrFail($data['employe_id']);
        $this->authorize('createFor', [Holiday::class, $employe]);

        $antraege = $this->holidays->beantragen(
            $actor,
            $employe,
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
            (bool) ($data['half_day'] ?? false),
            $data['comment'] ?? null,
        );

        return response()->json([
            'data' => $antraege->map(fn (Holiday $h) => $this->antragDaten($actor, $h->fresh(['employe', 'approved_by_user'])))->values(),
            'approved' => $antraege->every(fn (Holiday $h) => $h->approved),
        ], 201);
    }

    /**
     * POST /api/v1/holidays/{holiday}/approve
     */
    public function approve(Request $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('approve', $holiday);
        $this->holidays->genehmigen($holiday, $request->user());

        return $this->antwort($request, $holiday);
    }

    /**
     * POST /api/v1/holidays/{holiday}/reject
     */
    public function reject(Request $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('reject', $holiday);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->holidays->ablehnen($holiday, $request->user(), $data['reason'] ?? null);

        return $this->antwort($request, $holiday);
    }

    /**
     * POST /api/v1/holidays/{holiday}/cancel – Stornierung eines genehmigten, noch nicht begonnenen Urlaubs beantragen.
     */
    public function requestCancellation(Request $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('requestCancellation', $holiday);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->holidays->stornoBeantragen($holiday, $request->user(), $data['reason'] ?? null);

        return $this->antwort($request, $holiday);
    }

    /**
     * POST /api/v1/holidays/{holiday}/cancel-decision – Stornierungswunsch annehmen oder ablehnen.
     */
    public function decideCancellation(Request $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('decideCancellation', $holiday);
        $data = $request->validate(['decision' => ['required', 'in:approve,deny']]);
        $this->holidays->stornoEntscheiden($holiday, $request->user(), $data['decision'] === 'approve');

        return $this->antwort($request, $holiday);
    }

    /**
     * DELETE /api/v1/holidays/{holiday} – eigenen offenen/abgelehnten Antrag löschen bzw. als Genehmigende stornieren.
     */
    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $this->authorize('delete', $holiday);
        $this->holidays->stornieren($holiday, $request->user());

        return response()->json(null, 204);
    }

    // =========================================================================

    private function antwort(Request $request, Holiday $holiday): JsonResponse
    {
        $holiday = Holiday::withTrashed()->with(['employe', 'approved_by_user'])->findOrFail($holiday->id);

        return response()->json(['data' => $this->antragDaten($request->user(), $holiday)]);
    }

    private function antragDaten(User $actor, Holiday $h): array
    {
        $storniert = $h->trashed();

        return [
            'id' => $h->id,
            'employee' => $h->employe ? ['id' => $h->employe->id, 'name' => $h->employe->name] : null,
            'start_date' => $h->start_date->toDateString(),
            'end_date' => $h->end_date->toDateString(),
            'half_day' => (bool) $h->half_day,
            'days' => (float) ($h->days ?? 0),
            'comment' => $h->comment,
            'status' => $storniert ? 'storniert' : $h->status,
            'status_label' => $storniert ? 'Storniert' : $h->status_label,
            'approved_by' => $h->approved_by_user ? ['id' => $h->approved_by_user->id, 'name' => $h->approved_by_user->name] : null,
            'approved_at' => $h->approved_at?->toIso8601String(),
            'rejection_reason' => $h->rejection_reason,
            'cancellation_requested_at' => $h->cancellation_requested_at?->toIso8601String(),
            'cancellation_reason' => $h->cancellation_reason,
            'permissions' => [
                'approve' => !$storniert && $h->is_pending && $actor->can('approve', $h),
                'reject' => !$storniert && $h->is_pending && $actor->can('reject', $h),
                'delete' => !$storniert && $actor->can('delete', $h),
                'request_cancellation' => !$storniert && $actor->can('requestCancellation', $h),
                'decide_cancellation' => !$storniert && $actor->can('decideCancellation', $h),
            ],
        ];
    }

    private function kontoDaten(array $k): array
    {
        return [
            'year' => $k['jahr'],
            'entitlement' => (float) $k['anspruch'],
            'carry_over' => (float) $k['uebertrag'],
            'adjustments' => (float) $k['buchungen'],
            'taken' => (float) $k['genommen'],
            'requested' => (float) $k['beantragt'],
            'expired' => (float) $k['verfallen'],
            'expiring' => (float) $k['verfall_droht'],
            'expiry_date' => $k['verfallsdatum']?->toDateString(),
            'rest' => (float) $k['rest'],
            'rest_after_requests' => (float) $k['rest_nach_antraegen'],
        ];
    }

    private function jahr(Request $request): int
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);

        return $request->integer('year', now()->year);
    }
}
