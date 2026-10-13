<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\SetRosterWorkingTimeRequest;
use App\Http\Requests\API\v1\StoreRosterEventRequest;
use App\Http\Requests\API\v1\StoreRosterRequest;
use App\Models\Group;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\User;
use App\Services\Personal\Zeit\RosterService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 – Dienstplanung.
 *
 * - Mein Dienstplan: veröffentlichte Dienste und Termine der angemeldeten Person (alle Mitarbeitenden).
 * - Planung: Abteilungen, Wochenpläne, Arbeitszeiten, Termine, Veröffentlichung ("create roster",
 *   Abteilungsbezug über RosterPolicy).
 *
 * Gleiche Logik wie das Web-Frontend (RosterService). Arbeitszeiten werden als Zielzustand gesetzt (PUT),
 * damit Wiederholungen aus der Offline-Warteschlange der App keine gegenteilige Wirkung haben.
 */
class RosterApiController extends Controller
{
    /** Längster abrufbarer Zeitraum (Tage) für "Mein Dienstplan" und Planlisten. */
    private const MAX_DAYS = 92;

    public function __construct(private readonly RosterService $rosters)
    {
    }

    // ── Mein Dienstplan ──────────────────────────────────────────────────

    /**
     * GET /api/v1/roster/mine – veröffentlichte Dienste/Termine je Tag (Standard: aktuelle + zwei folgende Wochen).
     */
    public function mine(Request $request): JsonResponse
    {
        [$von, $bis] = $this->zeitraum($request, Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()->addWeeks(2)->endOfWeek());
        $user = $request->user();

        $daten = $this->rosters->persoenlich($user, $von, $bis);
        $abwesenheiten = $this->rosters->abwesenheitenJeTag(collect([$user->id]), $von, $bis)[$user->id] ?? [];

        $tage = [];
        for ($tag = $von->copy(); $tag->lte($bis); $tag->addDay()) {
            $datum = $tag->toDateString();
            $tage[] = [
                'date' => $datum,
                'holiday' => is_holiday($tag)['title'] ?? null,
                'absence' => $abwesenheiten[$datum] ?? null,
                'shifts' => $daten['zeiten']->filter(fn (WorkingTime $z) => $z->date->toDateString() === $datum)
                    ->map(fn (WorkingTime $z) => $this->arbeitszeitDaten($z) + [
                        'department' => $z->roster?->department ? ['id' => $z->roster->department->id, 'name' => $z->roster->department->name] : null,
                    ])->values(),
                'events' => $daten['termine']->filter(fn (RosterEvents $t) => $t->date->toDateString() === $datum && !$t->is_abwesenheit)
                    ->map(fn (RosterEvents $t) => $this->terminDaten($t))->values(),
            ];
        }

        $plaene = Roster::query()
            ->where('published', true)->where('type', 'normal')
            ->whereDate('start_date', '>=', $von->copy()->startOfWeek()->toDateString())
            ->whereDate('start_date', '<=', $bis->toDateString())
            ->whereIn('department_id', $user->groups_rel->pluck('id'))
            ->with('department')->orderBy('start_date')->get();

        return response()->json([
            'data' => [
                'from' => $von->toDateString(),
                'to' => $bis->toDateString(),
                'days' => $tage,
                'rosters' => $plaene->map(fn (Roster $r) => $this->planDaten($r))->values(),
            ],
        ]);
    }

    // ── Planung: Abteilungen & Pläne ─────────────────────────────────────

    /**
     * GET /api/v1/roster/departments – Abteilungen mit Dienstplan, die der Benutzer planen darf.
     */
    public function departments(Request $request): JsonResponse
    {
        $actor = $request->user();
        $abteilungen = Group::where('needsRoster', true)->orderBy('name')->get()
            ->filter(fn (Group $g) => $actor->can('manageDepartment', [Roster::class, $g]))
            ->values();

        return response()->json([
            'data' => $abteilungen->map(function (Group $g) {
                [$start, $ende] = $g->rosterDayWindow();

                return ['id' => $g->id, 'name' => $g->name, 'day_window' => ['start' => $start, 'end' => $ende]];
            }),
        ]);
    }

    /**
     * GET /api/v1/roster/departments/{department}/rosters – Pläne/Vorlagen einer Abteilung im Zeitraum
     * (Standard: 4 Wochen zurück bis 8 Wochen voraus; Vorlagen unabhängig vom Zeitraum).
     */
    public function index(Request $request, Group $department): JsonResponse
    {
        $this->authorize('manageDepartment', [Roster::class, $department]);
        $request->validate(['type' => ['nullable', 'in:normal,template']]);
        [$von, $bis] = $this->zeitraum($request, Carbon::now()->startOfWeek()->subWeeks(4), Carbon::now()->startOfWeek()->addWeeks(8)->endOfWeek());

        $plaene = $department->rosters()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->where(fn ($q) => $q->where('type', 'template')->orWhere(fn ($n) => $n
                ->whereDate('start_date', '>=', $von->copy()->startOfWeek()->toDateString())
                ->whereDate('start_date', '<=', $bis->toDateString())))
            ->withCount(['aenderungen as offene_aenderungen' => fn ($c) => $c->whereNull('notified_at')])
            ->with('department')
            ->orderBy('start_date')
            ->get();

        return response()->json(['data' => $plaene->map(fn (Roster $r) => $this->planDaten($r, true))->values()]);
    }

    /**
     * POST /api/v1/roster/rosters – Plan anlegen (optional aus Vorlage/Plan kopieren und in weitere Wochen übernehmen).
     */
    public function store(StoreRosterRequest $request): JsonResponse
    {
        $abteilung = Group::findOrFail($request->integer('department_id'));
        $this->authorize('manageDepartment', [Roster::class, $abteilung]);

        $vorlage = null;
        if ($request->filled('template_id')) {
            $vorlage = Roster::findOrFail($request->integer('template_id'));
            if ((int) $vorlage->department_id !== (int) $abteilung->id) {
                return $this->fehler('template_id', 'Die Vorlage gehört zu einer anderen Abteilung.');
            }
        }

        $start = Carbon::parse($request->input('start_date'))->startOfWeek();
        if ($request->input('type') === 'normal' && $this->wocheBelegt($abteilung, $start)) {
            return $this->fehler('start_date', 'Für diese Woche gibt es bereits einen Dienstplan.');
        }

        $roster = $this->rosters->anlegen($abteilung, $start, $request->input('type'), $request->input('comment'), $vorlage);

        $kopien = collect();
        if ($roster->type === 'normal' && $request->filled('additional_weeks')) {
            $kopien = $this->rosters->inWochenKopieren($roster, array_map(fn ($w) => Carbon::parse($w), $request->input('additional_weeks')));
        }

        return response()->json([
            'data' => $this->detail($roster->fresh(), $request->user()),
            'copies' => $kopien->map(fn (Roster $r) => $this->planDaten($r))->values(),
        ], 201);
    }

    /**
     * GET /api/v1/roster/rosters/{roster} – Plan mit Mitarbeitenden, Arbeitszeiten und Terminen.
     * Planende erhalten zusätzlich Konflikte, Wochenstunden, Checks und offene Änderungen.
     */
    public function show(Request $request, Roster $roster): JsonResponse
    {
        $this->authorize('view', $roster);

        return response()->json(['data' => $this->detail($roster, $request->user())]);
    }

    /**
     * DELETE /api/v1/roster/rosters/{roster}
     */
    public function destroy(Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);

        if ($roster->published && $roster->weekEnd()->isPast()) {
            return response()->json(['message' => 'Vergangene, veröffentlichte Dienstpläne können nicht gelöscht werden.'], 422);
        }

        $roster->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/roster/rosters/{roster}/copy – in weitere Wochen kopieren (vorhandene Wochen werden übersprungen).
     */
    public function copy(Request $request, Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);
        $data = $request->validate([
            'weeks' => ['required', 'array', 'min:1', 'max:26'],
            'weeks.*' => ['required', 'date_format:Y-m-d'],
        ]);

        $kopien = $this->rosters->inWochenKopieren($roster, array_map(fn ($w) => Carbon::parse($w), $data['weeks']));

        return response()->json(['data' => $kopien->map(fn (Roster $r) => $this->planDaten($r))->values()], 201);
    }

    // ── Veröffentlichung ─────────────────────────────────────────────────

    /**
     * POST /api/v1/roster/rosters/{roster}/publish – veröffentlichen (notify=false: ohne Benachrichtigung).
     */
    public function publish(Request $request, Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);
        $request->validate(['notify' => ['nullable', 'boolean']]);
        if ($roster->is_template) {
            return response()->json(['message' => 'Vorlagen können nicht veröffentlicht werden.'], 422);
        }

        $this->rosters->veroeffentlichen($roster, $request->user(), $request->boolean('notify', true));

        return response()->json(['data' => $this->planDaten($roster->fresh(), true)]);
    }

    /**
     * POST /api/v1/roster/rosters/{roster}/unpublish – Veröffentlichung zurückziehen (wieder Entwurf).
     */
    public function unpublish(Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);
        $this->rosters->zurueckziehen($roster);

        return response()->json(['data' => $this->planDaten($roster->fresh(), true)]);
    }

    /**
     * POST /api/v1/roster/rosters/{roster}/notify-changes – offene Änderungen den Betroffenen mitteilen.
     */
    public function notifyChanges(Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);

        return response()->json(['data' => ['notified' => $this->rosters->aenderungenMitteilen($roster)]]);
    }

    /**
     * POST /api/v1/roster/rosters/{roster}/revert – Änderungen seit der Veröffentlichung verwerfen.
     */
    public function revert(Request $request, Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);
        if (!$this->rosters->kannZuruecksetzen($roster)) {
            return response()->json(['message' => 'Für diesen Plan gibt es keinen gespeicherten Stand der Veröffentlichung.'], 422);
        }

        $this->rosters->aenderungenZuruecksetzen($roster);

        return response()->json(['data' => $this->detail($roster->fresh(), $request->user())]);
    }

    // ── Arbeitszeiten & Termine ──────────────────────────────────────────

    /**
     * PUT /api/v1/roster/rosters/{roster}/working-times – Arbeitszeit einer Person an einem Tag setzen.
     */
    public function setWorkingTime(SetRosterWorkingTimeRequest $request, Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);

        [$zeit, $hinweis] = $this->rosters->arbeitszeitSetzen(
            $roster,
            $request->integer('employe_id'),
            $request->input('date'),
            $request->input('start'),
            $request->input('end'),
            $request->input('function')
        );

        return response()->json(['data' => $this->arbeitszeitDaten($zeit->fresh()), 'warning' => $hinweis]);
    }

    /**
     * POST /api/v1/roster/rosters/{roster}/events – Termin für eine oder mehrere Personen (leer = Merkliste).
     * Personen mit Überschneidung werden übersprungen und in `skipped_employe_ids` gemeldet.
     */
    public function storeEvent(StoreRosterEventRequest $request, Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);

        $ids = array_map('intval', (array) $request->input('employe_ids', []));
        $fremd = array_values(array_diff($ids, $this->rosters->mitarbeitende($roster)->pluck('id')->all()));
        if ($fremd !== []) {
            return $this->fehler('employe_ids', 'Einzelne Personen gehören nicht zu dieser Abteilung.', ['invalid_employe_ids' => $fremd]);
        }

        $ergebnis = $this->rosters->termineAnlegen($roster, $request->safe()->only(['event', 'date', 'start', 'end']), $ids);

        return response()->json([
            'data' => $ergebnis['angelegt']->map(fn (RosterEvents $e) => $this->terminDaten($e->fresh()))->values(),
            'skipped_employe_ids' => $ergebnis['uebersprungen']->pluck('id')->values(),
        ], 201);
    }

    /**
     * PUT /api/v1/roster/events/{rosterEvent} – Termin ändern bzw. verschieben/zuweisen (employe_id null = Merkliste).
     * Überschneidung mit einem anderen Termin der Person → 409.
     */
    public function updateEvent(StoreRosterEventRequest $request, RosterEvents $rosterEvent): JsonResponse
    {
        $roster = $rosterEvent->roster;
        abort_if($roster === null, 404);
        $this->authorize('manage', $roster);

        if ($rosterEvent->is_abwesenheit) {
            return response()->json(['message' => 'Abwesenheiten werden automatisch aus Urlaub und Abwesenheiten übernommen und können nicht bearbeitet werden.'], 422);
        }

        $this->rosters->pruefeTag($roster, $request->input('date'));
        $employeId = $request->input('employe_id') !== null ? $request->integer('employe_id') : null;

        if ($employeId !== null && !$this->rosters->mitarbeitende($roster)->contains('id', $employeId)) {
            return $this->fehler('employe_id', 'Die Person gehört nicht zu dieser Abteilung.');
        }
        if ($employeId !== null && $this->rosters->kollision($roster, $employeId, $request->input('date'), $request->input('start'), $request->input('end'), $rosterEvent->id)) {
            return response()->json([
                'message' => 'Überschneidung mit einem anderen Termin der Person – nicht gespeichert.',
                'data' => $this->terminDaten($rosterEvent),
            ], 409);
        }

        $rosterEvent->update($request->safe()->only(['event', 'date', 'start', 'end']) + ['employe_id' => $employeId]);

        return response()->json(['data' => $this->terminDaten($rosterEvent->fresh())]);
    }

    /**
     * DELETE /api/v1/roster/events/{rosterEvent}
     */
    public function destroyEvent(RosterEvents $rosterEvent): JsonResponse
    {
        $roster = $rosterEvent->roster;
        abort_if($roster === null, 404);
        $this->authorize('manage', $roster);

        if ($rosterEvent->is_abwesenheit) {
            return response()->json(['message' => 'Abwesenheiten werden automatisch gepflegt und können nicht gelöscht werden.'], 422);
        }

        $rosterEvent->delete();

        return response()->json(null, 204);
    }

    // ── Kalender-Import ──────────────────────────────────────────────────

    /**
     * GET /api/v1/roster/rosters/{roster}/calendar-import – sichtbare Kalender und die Vorkommen des gewählten
     * Kalenders (?calendar_id, Standard: erster Kalender) in der Planwoche, Serien aufgelöst.
     */
    public function calendarImportPreview(Request $request, Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);
        $request->validate(['calendar_id' => ['nullable', 'integer']]);

        $kalender = $this->rosters->importKalender($request->user());
        $kalenderId = $request->filled('calendar_id') ? $request->integer('calendar_id') : $kalender->first()?->id;
        if ($kalenderId !== null && !$kalender->contains('id', $kalenderId)) {
            return $this->fehler('calendar_id', 'Der Kalender ist nicht verfügbar.');
        }

        $vorkommen = $kalenderId !== null ? $this->rosters->kalenderImportVorschau($roster, $kalenderId) : collect();

        return response()->json([
            'data' => [
                'calendars' => $kalender->map(fn ($k) => ['id' => $k->id, 'name' => $k->name, 'color' => $k->farbe])->values(),
                'calendar_id' => $kalenderId,
                'from' => $roster->start_date->toDateString(),
                'to' => $roster->start_date->copy()->addDays(6)->toDateString(),
                'occurrences' => $vorkommen->map(fn (object $v) => [
                    'key' => $v->selection_key,
                    'calendar_event_id' => $v->id,
                    'title' => $v->titel,
                    'location' => $v->ort,
                    'all_day' => $v->ganztaegig,
                    'start' => $v->beginn->toIso8601String(),
                    'end' => $v->ende->toIso8601String(),
                    'recurring' => $v->is_recurring,
                    'imported' => $v->imported,
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /api/v1/roster/rosters/{roster}/calendar-import – ausgewählte Vorkommen (Schlüssel aus der Vorschau)
     * als Termine in die Merkliste übernehmen. Bereits übernommene werden übersprungen (wiederholbar).
     */
    public function calendarImport(Request $request, Roster $roster): JsonResponse
    {
        $this->authorize('manage', $roster);
        $data = $request->validate([
            'keys' => ['required', 'array', 'min:1', 'max:200'],
            'keys.*' => ['required', 'string', 'max:64'],
        ]);

        $ergebnis = $this->rosters->kalenderImportieren($roster, $request->user(), $data['keys']);

        return response()->json([
            'data' => $ergebnis['angelegt']->map(fn (RosterEvents $e) => $this->terminDaten($e->fresh()))->values(),
            'skipped_keys' => $ergebnis['uebersprungen'],
            'invalid_keys' => $ergebnis['ungueltig'],
        ], $ergebnis['angelegt']->isEmpty() ? 200 : 201);
    }

    // =====================================================================

    /**
     * Vollständige Planansicht. Abwesenheitsgründe anderer Personen sehen nur Planende.
     */
    private function detail(Roster $roster, User $user): array
    {
        $roster->loadMissing('department');
        $planend = $user->can('manage', $roster);
        $raster = $this->rosters->raster($roster);
        [$fensterStart, $fensterEnde] = $roster->department->rosterDayWindow();

        $termine = collect($raster['events'])->map(function (array $e) use ($planend, $user) {
            $daten = [
                'id' => $e['id'],
                'employe_id' => $e['employe_id'],
                'date' => $e['date'],
                'start' => $e['start'],
                'end' => $e['end'],
                'event' => $e['event'],
                'is_absence' => $e['abwesend'],
                'is_break' => $e['pause'],
            ];
            if ($e['abwesend'] && !$planend && $e['employe_id'] !== $user->id) {
                $daten['event'] = 'abwesend';
            }

            return $daten;
        });

        $daten = $this->planDaten($roster) + [
            'day_window' => ['start' => $fensterStart, 'end' => $fensterEnde],
            'days' => array_map(fn (Carbon $t) => ['date' => $t->toDateString(), 'holiday' => is_holiday($t)['title'] ?? null], $roster->days()),
            'employees' => $this->rosters->mitarbeitende($roster)->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'working_times' => $raster['zeiten'],
            'events' => $termine->values(),
            'can_manage' => $planend,
        ];

        if ($planend) {
            $daten += [
                'conflicts' => $raster['konflikte'],
                'hours' => (object) array_map(fn ($h) => ['planned' => $h['geplant'], 'contract' => $h['vertrag']], (array) $raster['stunden']),
                'checks' => $raster['checks'],
                'open_changes' => $roster->aenderungen()->whereNull('notified_at')->count(),
                'can_revert' => $this->rosters->kannZuruecksetzen($roster),
            ];
        }

        return $daten;
    }

    private function planDaten(Roster $roster, bool $mitAenderungen = false): array
    {
        $daten = [
            'id' => $roster->id,
            'department' => $roster->department ? ['id' => $roster->department->id, 'name' => $roster->department->name] : null,
            'type' => $roster->type,
            'start_date' => $roster->start_date->toDateString(),
            'end_date' => $roster->weekEnd()->toDateString(),
            'week_label' => $this->rosters->wochenLabel($roster),
            'comment' => $roster->comment,
            'published' => (bool) $roster->published,
            'published_at' => $roster->published_at?->toIso8601String(),
        ];

        if ($mitAenderungen) {
            $daten['open_changes'] = $roster->offene_aenderungen ?? $roster->aenderungen()->whereNull('notified_at')->count();
        }

        return $daten;
    }

    private function arbeitszeitDaten(WorkingTime $zeit): array
    {
        return [
            'id' => $zeit->id,
            'roster_id' => $zeit->roster_id,
            'employe_id' => $zeit->employe_id,
            'date' => $zeit->date->toDateString(),
            'start' => $zeit->start?->format('H:i'),
            'end' => $zeit->end?->format('H:i'),
            'function' => $zeit->function,
        ];
    }

    private function terminDaten(RosterEvents $termin): array
    {
        return [
            'id' => $termin->id,
            'roster_id' => $termin->roster_id,
            'employe_id' => $termin->employe_id,
            'date' => $termin->date->toDateString(),
            'start' => $termin->start?->format('H:i'),
            'end' => $termin->end?->format('H:i'),
            'event' => $termin->event,
            'is_absence' => $termin->is_abwesenheit,
            'calendar_event_id' => $termin->ox_termin_id,
        ];
    }

    /**
     * Zeitraum aus ?from/&to (Y-m-d) mit Standardwerten, höchstens MAX_DAYS Tage.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function zeitraum(Request $request, Carbon $standardVon, Carbon $standardBis): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $von = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $standardVon->startOfDay();
        $bis = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : $standardBis->startOfDay();

        if ($bis->lt($von) || $von->diffInDays($bis) >= self::MAX_DAYS) {
            abort(response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['to' => ['Der Zeitraum darf höchstens '.self::MAX_DAYS.' Tage umfassen.']],
            ], 422));
        }

        return [$von, $bis];
    }

    private function wocheBelegt(Group $abteilung, Carbon $montag): bool
    {
        return Roster::where('department_id', $abteilung->id)
            ->where('type', 'normal')
            ->whereDate('start_date', $montag->toDateString())
            ->exists();
    }

    private function fehler(string $feld, string $meldung, array $extra = []): JsonResponse
    {
        return response()->json([
            'message' => 'Die übermittelten Daten sind ungültig.',
            'errors' => [$feld => [$meldung]],
        ] + $extra, 422);
    }
}
