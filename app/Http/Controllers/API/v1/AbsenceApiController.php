<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AbsenceSerie;
use App\Models\User;
use App\Services\AbsenceSerieService;
use App\Services\AbsenceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API v1 – Abwesenheiten (Dienstplan-App), wie die Abwesenheiten-Karte im Web.
 *
 * Route-Gruppe mit "view absences". Für andere melden: "create absences" (alle) oder Vorgesetzte/
 * Stellvertretungen für ihre Unterstellten, sonst nur für sich selbst; löschen dürfen
 * "delete absences" und die eintragende Person, solange das Ende nach heute liegt. Abwesenheiten aus
 * genehmigtem Urlaub werden über den Urlaub storniert. Logik: AbsenceService.
 *
 * Wiederkehrende Abwesenheiten (Serien): POST absences/series legt die Termine an, DELETE absences/series/{id}
 * löscht die zukünftigen Termine. Regeln wie im Web: AbsenceSerieService.
 */
class AbsenceApiController extends Controller
{
    public function __construct(
        private readonly AbsenceService $absences,
        private readonly AbsenceSerieService $serien,
    ) {
    }

    /**
     * GET /api/v1/absences – aktuelle und kommende Abwesenheiten aller Personen.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $fuerAndere = $this->absences->darfFuerAndereMelden($actor);
        $standard = (string) (settings('absence_reason_default', 'absences') ?: config('absences.absence_reason_default'));

        $abwesenheiten = Absence::query()
            ->whereDate('end', '>=', Carbon::today())
            ->with(['user', 'serie'])
            ->orderBy('start')
            ->get();

        return response()->json([
            'data' => $abwesenheiten->map(fn (Absence $a) => $this->daten($actor, $a))->values(),
            'options' => [
                'default_reason' => $standard,
                'reasons' => array_values(array_unique(array_filter([$standard, ...(array) config('absences.absence_sick_note')]))),
                'create_for_others' => $fuerAndere,
                'set_sick_note' => $actor->can('create absences'),
                'employees' => $fuerAndere
                    ? $this->absences->meldbarePersonen($actor)->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                    : [],
                'series' => [
                    'frequencies' => array_keys(AbsenceSerie::RHYTHMEN),
                    'weekdays' => array_keys(AbsenceSerie::WOCHENTAGE),
                    'intervals' => [1, 2, 3, 4],
                    'month_positions' => array_keys(AbsenceSerie::POSITIONEN),
                    'excluded_reasons' => array_values((array) config('absences.absence_sick_note')),
                    'earliest_start' => Carbon::today()->toDateString(),
                    'max_duration' => 'P1Y', // Ende höchstens ein Jahr nach Beginn
                ],
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
     * POST /api/v1/absences/series – wiederkehrende Abwesenheit: jeder Termin wird eine eintägige Abwesenheit.
     * Wochenenden, Feiertage und Tage mit vorhandener Abwesenheit werden übersprungen.
     */
    public function storeSeries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employe_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:64'],
            'start' => ['required', 'date', 'before_or_equal:end'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'show_substitution_plan' => ['nullable', 'boolean'],
            'frequency' => ['required', 'in:'.implode(',', array_keys(AbsenceSerie::RHYTHMEN))],
            'weekdays' => ['exclude_unless:frequency,woechentlich', 'required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'in:'.implode(',', array_keys(AbsenceSerie::WOCHENTAGE))],
            'interval' => ['exclude_unless:frequency,woechentlich', 'nullable', 'integer', 'min:1', 'max:4'],
            'month_position' => ['exclude_unless:frequency,monatlich', 'required', 'integer', 'in:'.implode(',', array_keys(AbsenceSerie::POSITIONEN))],
            'month_weekday' => ['exclude_unless:frequency,monatlich', 'required', 'integer', 'in:'.implode(',', array_keys(AbsenceSerie::WOCHENTAGE))],
        ]);

        $actor = $request->user();
        $ergebnis = $this->serien->anlegen($actor, [
            'users_id' => $data['employe_id'] ?? $actor->id,
            'reason' => $data['reason'],
            'start' => $data['start'],
            'end' => $data['end'],
            'showVertretungsplan' => ($data['show_substitution_plan'] ?? true) ? 1 : 0,
            'rhythmus' => $data['frequency'],
            'wochentage' => $data['weekdays'] ?? null,
            'intervall' => $data['interval'] ?? 1,
            'monat_position' => $data['month_position'] ?? null,
            'monat_wochentag' => $data['month_weekday'] ?? null,
        ]);

        /** @var AbsenceSerie $serie */
        $serie = $ergebnis['serie'];
        $termine = $serie->absences()->with(['user', 'serie'])->orderBy('start')->get();

        return response()->json([
            'data' => $this->serieDaten($actor, $serie),
            'created' => $ergebnis['angelegt'],
            'skipped' => $ergebnis['uebersprungen'],
            'absences' => $termine->map(fn (Absence $a) => $this->daten($actor, $a))->values(),
        ], 201);
    }

    /**
     * DELETE /api/v1/absences/series/{serie} – alle zukünftigen Termine löschen; vergangene und die Regel bleiben.
     */
    public function destroySeries(Request $request, AbsenceSerie $serie): JsonResponse
    {
        $actor = $request->user();
        abort_unless($this->serien->darfLoeschen($actor, $serie), 403, 'Dafür fehlt die Berechtigung.');

        return response()->json(['deleted' => $this->serien->zukuenftigeLoeschen($actor, $serie)]);
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
            'series' => $a->serie_id !== null && $a->serie ? $this->serieDaten($actor, $a->serie) : null,
        ];
    }

    private function serieDaten(User $actor, AbsenceSerie $serie): array
    {
        return [
            'id' => $serie->id,
            'frequency' => $serie->rhythmus,
            'weekdays' => $serie->rhythmus === 'woechentlich' ? array_map('intval', $serie->wochentage ?? []) : null,
            'interval' => $serie->rhythmus === 'woechentlich' ? $serie->intervall : null,
            'month_position' => $serie->monat_position,
            'month_weekday' => $serie->monat_wochentag,
            'start' => $serie->start->toDateString(),
            'end' => $serie->end->toDateString(),
            'description' => $serie->beschreibung(),
            'can_delete_future' => $this->serien->hatLoeschbareTermine($actor, $serie),
        ];
    }
}
