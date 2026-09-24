<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Services\PaedDiaryAppointmentService;
use App\Services\PaedDiaryCalendarService;
use App\Services\PaedDiaryEntryService;
use App\Services\PaedDiaryTaskService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API v1 – Planung im Pädagogischen Tagebuch: Aufgaben und Termine anlegen/bearbeiten,
 * Wiedervorlage offener Notizen, Schüler eines Eintrags ändern.
 *
 * Gleiche Logik wie das Web-Frontend (PaedDiaryTaskService, PaedDiaryAppointmentService).
 * Schreibende Aufrufe setzen einen Zielzustand, damit Wiederholungen aus der Offline-Warteschlange
 * der App keine gegenteilige Wirkung haben (zusätzlich Idempotency-Key).
 */
class PaedDiaryPlanningApiController extends Controller
{
    /** Grund der Eintrags-Pausen, die eine Wiedervorlage erzeugt. */
    public const RESUBMISSION_REASON = 'Wiedervorlage';

    /** Längster Zeitraum einer Wiedervorlage in Tagen. */
    private const RESUBMISSION_MAX_DAYS = 120;

    public function __construct(
        private PaedDiaryTaskService $tasks,
        private PaedDiaryAppointmentService $appointments,
        private PaedDiaryCalendarService $calendar,
        private PaedDiaryEntryService $entries
    ) {
    }

    // ── Aufgaben ─────────────────────────────────────────────────────────

    /**
     * POST /api/v1/paed-diary/tasks – Aufgabe für einen oder mehrere Schüler einer Klasse
     * (je Schüler eine Aufgabe).
     */
    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schueler_ids' => ['required', 'array', 'min:1', 'max:100'],
            'schueler_ids.*' => ['integer', 'exists:schueler,id'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'highlighted' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $students = Schueler::whereIn('id', array_unique($data['schueler_ids']))->get(['id', 'klasse_id']);

        $forbidden = $students->filter(fn ($s) => !$user->hasPaedClassAccess($s->klasse_id))->pluck('id')->values();
        if ($forbidden->isNotEmpty()) {
            return response()->json([
                'message' => 'Keine Berechtigung für einzelne Schüler.',
                'forbidden_schueler_ids' => $forbidden,
            ], 403);
        }

        $highlighted = $request->has('highlighted') ? $request->boolean('highlighted') : false;
        $created = DB::transaction(fn () => $students->groupBy('klasse_id')->flatMap(
            fn ($group, $klasseId) => $this->tasks->createForStudents((int) $klasseId, $group->pluck('id')->all(), $data, $user->id, $highlighted)
        ));

        return response()->json([
            'data' => $created->map(fn (PaedDiaryTask $t) => $this->tasks->toArray($t))->values(),
        ], 201);
    }

    /**
     * PUT /api/v1/paed-diary/tasks/{task} – Titel, Beschreibung, Fälligkeit, Hervorhebung ändern.
     */
    public function updateTask(Request $request, PaedDiaryTask $task): JsonResponse
    {
        abort_unless($request->user()->hasPaedClassAccess($task->klasse_id), 403, 'Dafür fehlt dir die Berechtigung.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'highlighted' => ['nullable', 'boolean'],
        ]);
        if ($request->has('highlighted')) {
            $data['highlighted'] = $request->boolean('highlighted');
        }

        return response()->json(['data' => $this->tasks->toArray($this->tasks->update($task, $data))]);
    }

    // ── Termine ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/paed-diary/appointments – Termin für Klassen, Lerngruppen und/oder einzelne Schüler
     * (z. B. Elterngespräch). Mit `pause_entries` werden offene Notizen der Betroffenen an den
     * Terminen pausiert.
     */
    public function storeAppointment(Request $request): JsonResponse
    {
        $data = $this->validateAppointment($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $appointment = DB::transaction(
            fn () => $this->appointments->create($data, $request->boolean('pause_entries'), $request->user())
        );
        $this->forgetAppointmentCaches($appointment);

        return response()->json(['data' => $this->appointmentToArray($appointment->fresh(), $request)], 201);
    }

    /**
     * PUT /api/v1/paed-diary/appointments/{appointment} – Termin vollständig ändern.
     */
    public function updateAppointment(Request $request, PaedDiaryAppointment $appointment): JsonResponse
    {
        abort_unless($this->appointments->canAccess($appointment, $request->user()), 403, 'Dafür fehlt dir die Berechtigung.');
        $data = $this->validateAppointment($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        DB::transaction(fn () => $this->appointments->update($appointment, $data, $request->boolean('pause_entries'), $request->user()));
        $this->forgetAppointmentCaches($appointment);

        return response()->json(['data' => $this->appointmentToArray($appointment->fresh(), $request)]);
    }

    /**
     * DELETE /api/v1/paed-diary/appointments/{appointment}?mode=all|only_this|this_and_future&date=
     */
    public function destroyAppointment(Request $request, PaedDiaryAppointment $appointment): JsonResponse
    {
        abort_unless($this->appointments->canAccess($appointment, $request->user()), 403, 'Dafür fehlt dir die Berechtigung.');
        $data = $request->validate([
            'mode' => ['nullable', 'in:all,only_this,this_and_future'],
            'date' => ['nullable', 'date_format:Y-m-d', 'required_if:mode,only_this,this_and_future'],
        ]);
        $this->forgetAppointmentCaches($appointment);
        DB::transaction(fn () => $this->appointments->delete($appointment, $data['mode'] ?? 'all', $data['date'] ?? null));

        return response()->json(null, 204);
    }

    // ── Wiedervorlage ────────────────────────────────────────────────────

    /**
     * PUT /api/v1/paed-diary/entries/{entry}/resubmission – offene Notiz bis zu einem Datum ausblenden
     * („ab Montag wieder zeigen“). Legt für jeden Schultag von `from` (Standard: heute) bis zum Vortag
     * von `resume_on` Pausen mit Grund „Wiedervorlage“ an – im Web wie in der App sichtbar.
     * `resume_on: null` hebt die Wiedervorlage ab `from` wieder auf. Ohne `schueler_id` für alle
     * Schüler der Notiz.
     */
    public function resubmission(Request $request, PaedDiaryEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry);
        $data = $request->validate([
            'resume_on' => ['present', 'nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'schueler_id' => ['nullable', 'integer'],
        ]);

        if ($entry->completed_at) {
            return $this->unprocessable('entry', 'Die Notiz ist bereits abgeschlossen.');
        }
        $studentIds = $entry->schueler()->pluck('schueler.id')->map(fn ($id) => (int) $id);
        if (isset($data['schueler_id'])) {
            if (!$studentIds->contains((int) $data['schueler_id'])) {
                return $this->unprocessable('schueler_id', 'Der Schüler gehört nicht zu dieser Notiz.');
            }
            $studentIds = collect([(int) $data['schueler_id']]);
        }

        $from = Carbon::parse($data['from'] ?? Carbon::today())->startOfDay();
        if ($from->lt($entry->datum)) {
            $from = $entry->datum->copy()->startOfDay();
        }
        $resumeOn = isset($data['resume_on']) ? Carbon::parse($data['resume_on'])->startOfDay() : null;
        if ($resumeOn && $resumeOn->lte($from)) {
            return $this->unprocessable('resume_on', 'Das Datum muss nach dem Beginn der Wiedervorlage liegen.');
        }
        if ($resumeOn && $from->diffInDays($resumeOn) > self::RESUBMISSION_MAX_DAYS) {
            return $this->unprocessable('resume_on', 'Eine Wiedervorlage ist höchstens ' . self::RESUBMISSION_MAX_DAYS . ' Tage möglich.');
        }

        $hasReason = Schema::hasColumn('paed_diary_entry_pauses', 'reason');
        $dates = [];

        DB::transaction(function () use ($entry, $studentIds, $from, $resumeOn, $hasReason, &$dates) {
            // Vorherige Wiedervorlage ab `from` ersetzen (so ist der Aufruf wiederholbar).
            $old = PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)
                ->whereIn('schueler_id', $studentIds)
                ->whereDate('date', '>=', $from->toDateString());
            if ($hasReason) {
                $old->where('reason', self::RESUBMISSION_REASON)->delete();
            } elseif (!$resumeOn) {
                $old->delete();
            }

            if (!$resumeOn) {
                return;
            }
            foreach (CarbonPeriod::create($from, $resumeOn->copy()->subDay()) as $day) {
                if ($day->isWeekend()) {
                    continue;
                }
                $dates[] = $day->toDateString();
                foreach ($studentIds as $studentId) {
                    $this->calendar->ensureEntryPause(
                        ['paed_diary_entry_id' => $entry->id, 'schueler_id' => $studentId, 'date' => $day->toDateString()],
                        $hasReason ? ['reason' => self::RESUBMISSION_REASON] : []
                    );
                }
            }
        });

        for ($week = $from->copy()->startOfWeek(); $week->lte($resumeOn ?? $from); $week->addWeek()) {
            $this->entries->forgetWeekCache($entry->klasse_id, $week);
        }

        return response()->json(['data' => [
            'entry_id' => $entry->id,
            'schueler_ids' => $studentIds->values(),
            'from' => $from->toDateString(),
            'resume_on' => $resumeOn?->toDateString(),
            'paused_dates' => $dates,
        ]]);
    }

    // ── Schüler eines Eintrags ───────────────────────────────────────────

    /**
     * PUT /api/v1/paed-diary/entries/{entry}/students/{schueler} – Schüler zum Eintrag hinzufügen
     * (gleiche Klasse wie der Eintrag; Lerngruppen-Einträge bestehen aus einem Eintrag je Klasse).
     */
    public function attachStudent(Request $request, PaedDiaryEntry $entry, Schueler $schueler): JsonResponse
    {
        $this->authorize('update', $entry);
        $this->authorize('update', $schueler);
        if ((int) $schueler->klasse_id !== (int) $entry->klasse_id) {
            return $this->unprocessable('schueler_id', 'Der Schüler gehört nicht zur Klasse des Eintrags.');
        }

        $entry->schueler()->syncWithoutDetaching([$schueler->id]);
        $this->entries->forgetWeekCache($entry->klasse_id, $entry->datum->copy());

        return $this->studentsResponse($entry);
    }

    /**
     * DELETE /api/v1/paed-diary/entries/{entry}/students/{schueler} – Schüler aus dem Eintrag entfernen
     * (inkl. seiner Pausen). Der letzte Schüler kann nicht entfernt werden – dann den Eintrag löschen.
     */
    public function detachStudent(Request $request, PaedDiaryEntry $entry, Schueler $schueler): JsonResponse
    {
        $this->authorize('update', $entry);
        $attached = $entry->schueler()->where('schueler.id', $schueler->id)->exists();
        if ($attached && $entry->schueler()->count() <= 1) {
            return $this->unprocessable('schueler_id', 'Ein Eintrag braucht mindestens einen Schüler. Lösche stattdessen den Eintrag.');
        }

        if ($attached) {
            DB::transaction(function () use ($entry, $schueler) {
                $entry->schueler()->detach($schueler->id);
                PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->where('schueler_id', $schueler->id)->delete();
            });
            $this->entries->forgetWeekCache($entry->klasse_id, $entry->datum->copy());
        }

        return $this->studentsResponse($entry);
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────

    private function studentsResponse(PaedDiaryEntry $entry): JsonResponse
    {
        return response()->json(['data' => [
            'entry_id' => $entry->id,
            'schueler_ids' => $entry->schueler()->pluck('schueler.id')->map(fn ($id) => (int) $id)->values(),
            'updated_at' => $entry->fresh()->updated_at?->toIso8601String(),
        ]]);
    }

    /**
     * @return array|JsonResponse Validierte Daten oder 422
     */
    private function validateAppointment(Request $request): array|JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_type' => ['nullable', 'in:daily,weekly,monthly'],
            'recurring_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurring_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'pause_entries' => ['nullable', 'boolean'],
            'class_ids' => ['array'],
            'class_ids.*' => ['integer', 'exists:klassen,id'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'exists:paed_diary_class_groups,id'],
            'schueler_ids' => ['array'],
            'schueler_ids.*' => ['integer', 'exists:schueler,id'],
        ]);
        $data['is_recurring'] = $request->boolean('is_recurring');
        if ($data['is_recurring'] && empty($data['recurring_type'])) {
            return $this->unprocessable('recurring_type', 'Bitte gib an, wie oft sich der Termin wiederholt.');
        }

        // Nur zugängliche Klassen/Schüler (sonst 403 statt stillem Verwerfen wie im Web-Formular)
        $user = $request->user();
        $data['klasse_ids'] = $data['class_ids'] ?? [];
        foreach ($data['klasse_ids'] as $klasseId) {
            abort_unless($user->hasPaedClassAccess((int) $klasseId), 403, 'Dafür fehlt dir die Berechtigung.');
        }
        $students = Schueler::whereIn('id', $data['schueler_ids'] ?? [])->get(['id', 'klasse_id']);
        foreach ($students as $student) {
            abort_unless($user->hasPaedClassAccess($student->klasse_id), 403, 'Dafür fehlt dir die Berechtigung.');
        }
        if (empty($data['klasse_ids']) && empty($data['group_ids']) && empty($data['schueler_ids'])) {
            return $this->unprocessable('schueler_ids', 'Wähle mindestens eine Klasse, Lerngruppe oder einen Schüler.');
        }

        return $data;
    }

    private function appointmentToArray(PaedDiaryAppointment $a, Request $request): array
    {
        $a->load(['klassen:id', 'groups:id', 'schueler:id']);

        return [
            'id' => $a->id,
            'title' => $a->title,
            'description' => $a->description,
            'start_date' => $a->start_date?->toDateString(),
            'start_time' => $a->start_time?->format('H:i'),
            'end_time' => $a->end_time?->format('H:i'),
            'is_recurring' => (bool) $a->is_recurring,
            'recurring_type' => $a->recurring_type,
            'recurring_interval' => $a->recurring_interval,
            'recurring_end_date' => $a->recurring_end_date?->toDateString(),
            'pause_entries' => (bool) ($a->pause_entries ?? false),
            'class_ids' => $a->klassen->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'group_ids' => $a->groups->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'schueler_ids' => $a->schueler->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'is_own' => (int) $a->user_id === (int) $request->user()->id,
        ];
    }

    private function forgetAppointmentCaches(PaedDiaryAppointment $appointment): void
    {
        $classIds = $appointment->klassen()->pluck('klassen.id')
            ->merge($appointment->schueler()->pluck('schueler.klasse_id'))
            ->filter()->unique();
        foreach ($classIds as $klasseId) {
            $this->entries->forgetWeekCache($klasseId, $appointment->start_date->copy());
        }
    }

    private function unprocessable(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Die übermittelten Daten sind ungültig.',
            'errors' => [$field => [$message]],
        ], 422);
    }
}
