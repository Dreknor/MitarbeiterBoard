<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Services\AbsenceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API v1 – Abwesenheiten (Dienstplan-App), wie die Abwesenheiten-Karte im Web.
 *
 * Route-Gruppe mit "view absences". Ohne "create absences" nur für sich selbst; löschen dürfen
 * "delete absences" und die eintragende Person, solange das Ende nach heute liegt. Abwesenheiten aus
 * genehmigtem Urlaub werden über den Urlaub storniert. Logik: AbsenceService.
 */
class AbsenceApiController extends Controller
{
    public function __construct(private readonly AbsenceService $absences)
    {
    }

    /**
     * GET /api/v1/absences – aktuelle und kommende Abwesenheiten aller Personen.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $fuerAndere = $actor->can('create absences');
        $standard = (string) (settings('absence_reason_default', 'absences') ?: config('absences.absence_reason_default'));

        $abwesenheiten = Absence::query()
            ->whereDate('end', '>=', Carbon::today())
            ->with('user')
            ->orderBy('start')
            ->get();

        return response()->json([
            'data' => $abwesenheiten->map(fn (Absence $a) => $this->daten($actor, $a))->values(),
            'options' => [
                'default_reason' => $standard,
                'reasons' => array_values(array_unique(array_filter([$standard, ...(array) config('absences.absence_sick_note')]))),
                'create_for_others' => $fuerAndere,
                'set_sick_note' => $fuerAndere,
                'employees' => $fuerAndere
                    ? User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                    : [],
            ],
        ]);
    }

    /**
     * POST /api/v1/absences – Abwesenheit melden (schließt sie direkt an eine gleiche an, wird diese verlängert).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employe_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:64'],
            'start' => ['required', 'date', 'before_or_equal:end'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'show_substitution_plan' => ['nullable', 'boolean'],
            'sick_note_required' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        $absence = $this->absences->melden($actor, [
            'users_id' => $data['employe_id'] ?? $actor->id,
            'reason' => $data['reason'],
            'start' => $data['start'],
            'end' => $data['end'],
            'showVertretungsplan' => ($data['show_substitution_plan'] ?? true) ? 1 : 0,
            'sick_note_required' => isset($data['sick_note_required']) ? (int) $data['sick_note_required'] : null,
        ]);

        return response()->json(['data' => $this->daten($actor, $absence->fresh('user'))], 201);
    }

    /**
     * DELETE /api/v1/absences/{absence}
     */
    public function destroy(Request $request, Absence $absence): JsonResponse
    {
        $actor = $request->user();
        abort_unless($this->absences->darfLoeschen($actor, $absence), 403, 'Dafür fehlt die Berechtigung.');

        if ($absence->holiday_id !== null) {
            throw ValidationException::withMessages(['absence' => 'Diese Abwesenheit stammt aus einem Urlaub – bitte den Urlaub stornieren.']);
        }

        $this->absences->loeschen($actor, $absence);

        return response()->json(null, 204);
    }

    private function daten(User $actor, Absence $a): array
    {
        $heute = Carbon::today();

        return [
            'id' => $a->id,
            'employee' => ['id' => $a->users_id, 'name' => $a->user?->name],
            'reason' => $a->reason,
            'start' => $a->start->toDateString(),
            'end' => $a->end->toDateString(),
            'days' => $a->days,
            'today' => $a->start->lte($heute) && $a->end->gte($heute),
            'show_substitution_plan' => (bool) $a->showVertretungsplan,
            'from_holiday' => $a->holiday_id !== null,
            'is_own' => (int) $a->users_id === $actor->id,
            'can_delete' => $a->holiday_id === null && $this->absences->darfLoeschen($actor, $a) && $this->absences->loeschbar($a),
        ];
    }
}
