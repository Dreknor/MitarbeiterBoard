<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Klasse;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use App\Services\PaedDiaryCalendarService;
use App\Services\PaedDiaryEntryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * API v1 – Wochenansicht (Kalender) des Pädagogischen Tagebuchs.
 *
 * Entspricht der Web-Wochenansicht (PaedDiaryController::weekData): offene Notizen laufen über
 * mehrere Tage, können je Schüler und Tag pausiert oder abgeschlossen werden; dazu Abwesenheiten,
 * Tagespausen der Klasse, Abhak-Spalten, offene Aufgaben und Termine.
 *
 * Alle schreibenden Endpunkte setzen einen Zielzustand (statt umzuschalten), damit Wiederholungen
 * aus der Offline-Warteschlange der App keine gegenteilige Wirkung haben.
 */
class PaedDiaryWeekApiController extends Controller
{
    public function __construct(
        private PaedDiaryCalendarService $calendar,
        private PaedDiaryEntryService $entries
    ) {
    }

    /**
     * GET /api/v1/paed-diary/week?class_id=|group_id=&week_start=
     */
    public function week(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'week_start' => ['nullable', 'date'],
        ]);
        $user = $request->user();
        [$klassen, $group] = $this->resolveClasses($request, $user);

        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->input('week_start'))->startOfWeek()
            : Carbon::now()->startOfWeek();

        $week = $this->calendar->loadWeek($klassen, $weekStart);
        $appointments = $this->calendar->appointments(
            $klassen->pluck('id')->all(),
            $group?->id,
            $week['week_start'],
            $week['week_start']->copy()->addDays(6)
        );

        $hiddenCategoryIds = [];
        try {
            $hiddenCategoryIds = $user->hiddenPaedDiaryCategories()->pluck('paed_diary_categories.id')
                ->map(fn ($id) => (int) $id)->values()->all();
        } catch (\Throwable $_) {
            $hiddenCategoryIds = [];
        }

        $dayPauses = $week['class_day_pauses'];

        return response()->json(['data' => [
            'week_start' => $week['week_start']->toDateString(),
            'week_end' => $week['week_end']->toDateString(),
            'group' => $group ? ['id' => $group->id, 'name' => $group->name] : null,
            'classes' => $klassen->map(fn ($k) => [
                'id' => $k->id,
                'name' => $k->name,
                'short_name' => $k->kuerzel,
                'color' => $k->color,
            ])->values(),
            'days' => $week['days']->map(fn ($d) => [
                'date' => $d['date'],
                'is_holiday' => (bool) $d['is_ferien'],
                'holiday_name' => $d['ferien_name'],
            ])->values(),
            'students' => $week['schueler']->map(fn (Schueler $s) => [
                'id' => $s->id,
                'firstname' => $s->vorname,
                'lastname' => $s->nachname,
                'class_id' => $s->klasse_id,
                'current_grading' => $s->grading_stage ? [
                    'stage_id' => $s->grading_stage->id,
                    'stage_title' => $s->grading_stage->name,
                    'symbol' => $s->grading_stage->symbol,
                    'badge_url' => $s->grading_stage->image_url,
                ] : null,
                'absence_alerts' => collect($this->calendar->absenceAlerts($s->id))->map(fn ($a) => [
                    'type' => $a['type'] ?? null,
                    'label' => $a['label'] ?? '',
                    'severity' => $a['severity'] ?? 'warning',
                    'summary' => $a['summary'] ?? '',
                ])->values(),
            ])->values(),
            'entries' => $week['entries']->map(fn (PaedDiaryEntry $e) => [
                'id' => $e->id,
                'class_id' => $e->klasse_id,
                'schueler_ids' => $e->schueler->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'entry_date' => $e->datum->toDateString(),
                'content' => $e->content,
                'category_id' => $e->category_id,
                'category_name' => $e->category?->name,
                'category_color' => $e->category?->color,
                'created_by_name' => $e->user?->name,
                'is_own' => (int) $e->user_id === (int) $user->id,
                'is_completed' => $e->completed_at !== null,
                'completed_at' => $e->completed_at?->toIso8601String(),
            ])->sortBy([['entry_date', 'asc'], ['id', 'asc']])->values(),
            'pauses' => $week['pauses']->map(fn ($p) => [
                'entry_id' => $p->paed_diary_entry_id,
                'schueler_id' => $p->schueler_id,
                'date' => $p->date->toDateString(),
                // z. B. „Ferien“, „Termin“, „Wiedervorlage“ oder null (einzeln pausiert)
                'reason' => $p->reason ?? null,
            ])->values(),
            'absences' => $week['absences']->map(fn ($a) => [
                'schueler_id' => $a->schueler_id,
                'date' => $a->datum->toDateString(),
            ])->values(),
            'day_pauses' => $dayPauses->map(fn ($p) => [
                'class_id' => $p->klasse_id,
                'date' => $p->date->toDateString(),
                'reason' => $p->reason ?: 'Veranstaltung',
            ])->values(),
            'columns' => $week['columns']->map(fn (PaedDiaryColumn $c) => [
                'id' => $c->id,
                'class_id' => $c->klasse_id,
                'name' => $c->name,
                'type' => in_array($c->type, ['boolean', 'ampel'], true) ? $c->type : 'text',
                'category' => $c->category,
            ])->values(),
            'column_values' => $week['column_values']->map(fn ($v) => [
                'column_id' => $v->paed_diary_column_id,
                'schueler_id' => $v->schueler_id,
                'date' => $v->datum->toDateString(),
                'value' => $v->value,
            ])->values(),
            'tasks' => $week['tasks']->map(fn (PaedDiaryTask $t) => [
                'id' => $t->id,
                'schueler_id' => $t->schueler_id,
                'title' => $t->title,
                'description' => $t->description,
                'due_date' => $t->due_date?->toDateString(),
                'highlighted' => (bool) $t->highlighted,
            ])->values(),
            'appointments' => collect($appointments)->map(fn ($a) => [
                'id' => $a['id'],
                'title' => $a['title'],
                'description' => $a['description'],
                'date' => $a['date'],
                'start_time' => $this->time($a['start_time'] ?? null),
                'end_time' => $this->time($a['end_time'] ?? null),
                'is_recurring' => (bool) ($a['is_recurring'] ?? false),
                'recurring_type' => $a['recurring_type'] ?? null,
                'pause_entries' => (bool) ($a['pause_entries'] ?? false),
                'is_own' => (int) ($a['user_id'] ?? 0) === (int) $user->id,
                'class_ids' => collect($a['klassen'])->pluck('id')->values(),
                'group_ids' => collect($a['groups'])->pluck('id')->values(),
                'schueler_ids' => collect($a['schueler'])->pluck('id')->values(),
            ])->values(),
            'hidden_category_ids' => $hiddenCategoryIds,
        ]]);
    }

    /**
     * POST /api/v1/paed-diary/entries/{entry}/complete – offene Notiz abschließen
     * (optional nur für einen Schüler; übrige Schüler behalten die Notiz offen).
     */
    public function complete(Request $request, PaedDiaryEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry);
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'schueler_id' => ['nullable', 'integer'],
        ]);
        $schuelerId = isset($data['schueler_id']) ? (int) $data['schueler_id'] : null;

        // Bereits abgeschlossen bzw. Schüler schon herausgelöst → nichts zu tun (Wiederholung).
        $attached = $schuelerId === null || $entry->schueler()->where('schueler.id', $schuelerId)->exists();
        if ($entry->completed_at || !$attached) {
            return response()->json(['data' => ['entry_id' => $entry->id, 'completed' => true]]);
        }

        $completedAt = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : Carbon::now();
        $date = $entry->datum->copy();

        DB::transaction(fn () => $this->entries->complete($entry, $completedAt, $schuelerId));

        $this->entries->forgetWeekCache($entry->klasse_id, $date);
        $this->entries->forgetWeekCache($entry->klasse_id, $completedAt);

        return response()->json(['data' => ['entry_id' => $entry->id, 'completed' => true]]);
    }

    /**
     * PUT /api/v1/paed-diary/entries/{entry}/pause – Notiz für einen Schüler an einem Tag
     * ausblenden (`paused=true`) oder wieder anzeigen (`paused=false`).
     */
    public function pause(Request $request, PaedDiaryEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry);
        $data = $request->validate([
            'schueler_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'paused' => ['required', 'boolean'],
        ]);

        if ($entry->completed_at) {
            return $this->unprocessable('entry', 'Die Notiz ist bereits abgeschlossen.');
        }
        if (!$entry->schueler()->where('schueler.id', $data['schueler_id'])->exists()) {
            return $this->unprocessable('schueler_id', 'Der Schüler gehört nicht zu dieser Notiz.');
        }
        $date = Carbon::parse($data['date'])->toDateString();
        if ($date < $entry->datum->toDateString()) {
            return $this->unprocessable('date', 'Das Datum liegt vor dem Beginn der Notiz.');
        }

        $keys = ['paed_diary_entry_id' => $entry->id, 'schueler_id' => (int) $data['schueler_id'], 'date' => $date];
        if ($request->boolean('paused')) {
            $this->calendar->ensureEntryPause($keys);
        } else {
            PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)
                ->where('schueler_id', $keys['schueler_id'])
                ->whereDate('date', $date)
                ->delete();
        }
        $this->entries->forgetWeekCache($entry->klasse_id, Carbon::parse($date));

        return response()->json(['data' => [
            'entry_id' => $entry->id,
            'schueler_id' => $keys['schueler_id'],
            'date' => $date,
            'paused' => $request->boolean('paused'),
        ]]);
    }

    /**
     * PUT /api/v1/paed-diary/absences – Abwesenheit eines Schülers an einem Tag setzen/aufheben.
     * Beim Setzen werden seine offenen Notizen an diesem Tag pausiert.
     */
    public function absence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schueler_id' => ['required', 'integer', 'exists:schueler,id'],
            'date' => ['required', 'date'],
            'absent' => ['required', 'boolean'],
        ]);
        $schueler = Schueler::findOrFail($data['schueler_id']);
        $this->authorize('update', $schueler);
        if (!$schueler->klasse_id) {
            return $this->unprocessable('schueler_id', 'Der Schüler ist keiner Klasse zugeordnet.');
        }

        $date = Carbon::parse($data['date'])->toDateString();
        $result = $this->calendar->setAbsence(
            (int) $schueler->id,
            (int) $schueler->klasse_id,
            $date,
            $request->boolean('absent'),
            $request->user()->id
        );
        $this->entries->forgetWeekCache($schueler->klasse_id, Carbon::parse($date));

        return response()->json(['data' => [
            'schueler_id' => $schueler->id,
            'date' => $date,
            'absent' => $result['absent'],
            'paused_entry_ids' => collect($result['pauses'])->pluck('entry_id')->values(),
            'resumed_entry_ids' => array_values($result['removed_entry_ids']),
        ]]);
    }

    /**
     * PUT /api/v1/paed-diary/day-pauses – alle offenen Notizen der Klasse/Gruppe an einem Tag
     * pausieren (z. B. Wandertag) bzw. die Tagespause aufheben.
     */
    public function dayPause(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'paused' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        [$klassen] = $this->resolveClasses($request, $user);
        $date = Carbon::parse($data['date'])->toDateString();

        if ($request->boolean('paused')) {
            $this->calendar->pauseClassDay($klassen, $date, $data['reason'] ?? null, $user->id);
        } else {
            $this->calendar->unpauseClassDay($klassen, $date);
        }
        foreach ($klassen as $klasse) {
            $this->entries->forgetWeekCache($klasse->id, Carbon::parse($date));
        }

        return response()->json(['data' => [
            'class_ids' => $klassen->pluck('id')->values(),
            'date' => $date,
            'paused' => $request->boolean('paused'),
            'reason' => $request->boolean('paused') ? (trim($data['reason'] ?? '') ?: 'Veranstaltung') : null,
        ]]);
    }

    /**
     * PUT /api/v1/paed-diary/column-values – Wert einer Abhak-Spalte setzen
     * (boolean: "1"/leer; ampel: "1" ja, "2" in Bearbeitung, "3" nein, leer; text: frei).
     */
    public function columnValue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'column_id' => ['required', 'integer', 'exists:paed_diary_columns,id'],
            'schueler_id' => ['required', 'integer', 'exists:schueler,id'],
            'date' => ['required', 'date'],
            'value' => ['nullable', 'string', 'max:255'],
        ]);
        $column = PaedDiaryColumn::findOrFail($data['column_id']);
        abort_unless($request->user()->hasPaedClassAccess($column->klasse_id), 403, 'Dafür fehlt dir die Berechtigung.');

        $schueler = Schueler::whereKey($data['schueler_id'])->where('klasse_id', $column->klasse_id)->first();
        if (!$schueler) {
            return $this->unprocessable('schueler_id', 'Der Schüler gehört nicht zur Klasse dieser Spalte.');
        }

        $value = $data['value'] ?? null;
        $allowed = match ($column->type) {
            'boolean' => [null, '', '1'],
            'ampel' => [null, '', '1', '2', '3'],
            default => null,
        };
        if ($allowed !== null && !in_array($value, $allowed, true)) {
            return $this->unprocessable('value', 'Ungültiger Wert für diese Spalte.');
        }

        $date = Carbon::parse($data['date']);
        $existing = PaedDiaryColumnValue::where('paed_diary_column_id', $column->id)
            ->where('schueler_id', $schueler->id)
            ->whereDate('datum', $date->toDateString())
            ->first();
        if ($existing) {
            $existing->update(['value' => $value]);
        } else {
            PaedDiaryColumnValue::create([
                'paed_diary_column_id' => $column->id,
                'schueler_id' => $schueler->id,
                'datum' => $date->toDateString(),
                'value' => $value,
            ]);
        }
        $this->entries->forgetWeekCache($column->klasse_id, $date);

        return response()->json(['data' => [
            'column_id' => $column->id,
            'schueler_id' => $schueler->id,
            'date' => $date->toDateString(),
            'value' => $value,
        ]]);
    }

    /**
     * POST /api/v1/paed-diary/tasks/{task}/close – offene Aufgabe erledigen/ausblenden.
     */
    public function closeTask(Request $request, PaedDiaryTask $task): JsonResponse
    {
        abort_unless($request->user()->hasPaedClassAccess($task->klasse_id), 403, 'Dafür fehlt dir die Berechtigung.');

        if ($task->status !== 'closed') {
            $task->update(['status' => 'closed', 'highlighted' => false, 'closed_at' => now()]);
            $this->entries->forgetWeekCache($task->klasse_id, Carbon::now());
        }

        return response()->json(['data' => ['id' => $task->id, 'status' => 'closed']]);
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────

    /**
     * Klassen aus `class_id` oder `group_id` (Lerngruppe des Benutzers) ermitteln.
     *
     * @return array{0: Collection, 1: PaedDiaryClassGroup|null}
     */
    private function resolveClasses(Request $request, User $user): array
    {
        if ($request->filled('group_id')) {
            $group = PaedDiaryClassGroup::with('klassen:id,name,kuerzel,color')
                ->whereKey($request->integer('group_id'))
                ->where('user_id', $user->id)
                ->firstOrFail();
            $klassen = $group->klassen->filter(fn ($k) => $user->hasPaedClassAccess($k->id))->values();
            if ($klassen->isEmpty()) {
                abort(403, 'Dafür fehlt dir die Berechtigung.');
            }

            return [$klassen, $group];
        }

        if (!$request->filled('class_id')) {
            abort(response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['class_id' => ['class_id oder group_id ist erforderlich.']],
            ], 422));
        }

        $klasse = Klasse::findOrFail($request->integer('class_id'));
        abort_unless($user->hasPaedClassAccess($klasse->id), 403, 'Dafür fehlt dir die Berechtigung.');

        return [collect([$klasse]), null];
    }

    private function time(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr(Carbon::parse((string) $value)->format('H:i'), 0, 5);
    }

    private function unprocessable(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Die übermittelten Daten sind ungültig.',
            'errors' => [$field => [$message]],
        ], 422);
    }
}
