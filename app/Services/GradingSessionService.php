<?php

namespace App\Services;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingSessionStudent;
use App\Models\GradingTeacherAssessment;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Graduierungs-Sessions (Dokumentation): Starten/Fortsetzen, Beantwortungsreihenfolge,
 * Teilnehmer und Abschluss je Schüler.
 *
 * Gemeinsam genutzt vom Web-Frontend (GradingDocumentationController) und der API v1
 * (GradingApiController), damit beide Wege identisches Verhalten haben.
 *
 * Teilnehmer einer Gruppensession: Ohne Einträge in grading_session_students umfasst sie
 * – wie bisher im Web – alle Schüler der Klasse. Die API kann eine Teilmenge festlegen.
 */
class GradingSessionService
{
    /**
     * Startet eine Gruppensession oder setzt die eigene offene Session für Klasse/Lerngruppe fort.
     * Beim Fortsetzen wird die gewählte Beantwortungsreihenfolge übernommen (wie im Web).
     *
     * @param  int[]|null  $schuelerIds  Teilnehmer (null = alle Schüler der Klasse)
     * @param  bool  $keepModeWhenMissing  API: ohne Angabe behält eine fortgesetzte Session ihren Modus
     * @return array{0: GradingDocumentationSession, 1: bool}  Session und "fortgesetzt"
     */
    public function startGroupSession(
        User $user,
        Klasse $klasse,
        ?int $groupId,
        ?string $answerOrderMode,
        ?array $schuelerIds = null,
        bool $keepModeWhenMissing = false
    ): array {
        $modeGiven = $answerOrderMode !== null;
        $answerOrderMode = GradingDocumentationSession::normalizeAnswerOrderMode($answerOrderMode);

        return DB::transaction(function () use ($user, $klasse, $groupId, $answerOrderMode, $schuelerIds, $modeGiven, $keepModeWhenMissing) {
            $existingSession = GradingDocumentationSession::where('user_id', $user->id)
                ->where('klasse_id', $klasse->id)
                ->where('type', 'group')
                ->where('group_id', $groupId)
                ->whereNull('completed_at')
                ->first();

            if ($existingSession) {
                $changeMode = $modeGiven || !$keepModeWhenMissing;
                if ($changeMode && $existingSession->answer_order_mode !== $answerOrderMode) {
                    $existingSession->update([
                        'answer_order_mode' => $answerOrderMode,
                    ]);
                    $existingSession->refresh();
                }

                // Neue Teilnehmer ergänzen (nie entfernen – es können bereits Antworten vorliegen).
                // Sessions ohne Teilnehmerliste umfassen ohnehin die ganze Klasse.
                if ($schuelerIds !== null && $existingSession->participants()->exists()) {
                    $this->addParticipants($existingSession, $schuelerIds);
                }

                return [$existingSession, true];
            }

            $session = GradingDocumentationSession::create([
                'klasse_id' => $klasse->id,
                'grading_system_id' => $klasse->grading_system_id,
                'user_id' => $user->id,
                'type' => 'group',
                'answer_order_mode' => $answerOrderMode,
                'group_id' => $groupId,
                'started_at' => now(),
            ]);

            if ($schuelerIds !== null) {
                $this->addParticipants($session, $schuelerIds);
            }

            return [$session, false];
        });
    }

    /**
     * Startet eine individuelle Session oder setzt die eigene offene Session für den Schüler fort.
     *
     * @return array{0: GradingDocumentationSession, 1: bool}
     */
    public function startIndividualSession(User $user, Klasse $klasse, int $schuelerId): array
    {
        $existingSession = GradingDocumentationSession::where('user_id', $user->id)
            ->where('klasse_id', $klasse->id)
            ->where('type', 'individual')
            ->where('schueler_id', $schuelerId)
            ->whereNull('completed_at')
            ->first();

        if ($existingSession) {
            if ($existingSession->answer_order_mode !== GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT) {
                $existingSession->update([
                    'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
                ]);
                $existingSession->refresh();
            }

            return [$existingSession, true];
        }

        $session = GradingDocumentationSession::create([
            'klasse_id' => $klasse->id,
            'grading_system_id' => $klasse->grading_system_id,
            'user_id' => $user->id,
            'type' => 'individual',
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
            'schueler_id' => $schuelerId,
            'started_at' => now(),
        ]);

        return [$session, false];
    }

    /**
     * Ändert die Beantwortungsreihenfolge. Liefert false, wenn der Modus für die Session nicht
     * verfügbar ist (individuelle Sessions nur "by_student").
     * Die Prüfung auf abgeschlossene Sessions erfolgt im Aufrufer (unterschiedliche Fehlercodes Web/API).
     */
    public function changeAnswerOrderMode(GradingDocumentationSession $session, string $mode): bool
    {
        $mode = GradingDocumentationSession::normalizeAnswerOrderMode($mode);

        if (!$session->canUseAnswerOrderMode($mode)) {
            return false;
        }

        $session->update([
            'answer_order_mode' => $mode,
        ]);
        $session->refresh();

        return true;
    }

    /**
     * Teilnehmende Schüler (sortiert nach Nachname, Vorname).
     */
    public function participants(GradingDocumentationSession $session): Collection
    {
        if ($session->isIndividualSession()) {
            return Schueler::whereKey($session->schueler_id)->get();
        }

        $ids = $session->participants()->pluck('schueler_id');

        return Schueler::query()
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->where('klasse_id', $session->klasse_id))
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    public function isParticipant(GradingDocumentationSession $session, int $schuelerId): bool
    {
        if ($session->isIndividualSession()) {
            return (int) $session->schueler_id === $schuelerId;
        }

        if ($session->participants()->exists()) {
            return $session->participants()->where('schueler_id', $schuelerId)->exists();
        }

        return Schueler::whereKey($schuelerId)->where('klasse_id', $session->klasse_id)->exists();
    }

    /**
     * IDs der Schüler, deren Bewertung in der Session abgeschlossen ist.
     */
    public function finalizedIds(GradingDocumentationSession $session): Collection
    {
        if ($session->isCompleted()) {
            return $this->participants($session)->pluck('id')->map(fn ($id) => (int) $id);
        }

        return $session->participants()->whereNotNull('finalized_at')->pluck('schueler_id')->map(fn ($id) => (int) $id);
    }

    public function isFinalized(GradingDocumentationSession $session, int $schuelerId): bool
    {
        return $session->isCompleted()
            || $session->participants()->where('schueler_id', $schuelerId)->whereNotNull('finalized_at')->exists();
    }

    /**
     * Schließt die Bewertung eines Schülers in einer Gruppensession ab. Sind alle Teilnehmer
     * abgeschlossen, wird die Session abgeschlossen. Individuelle Sessions werden direkt abgeschlossen.
     *
     * @return bool  true, wenn die Session nun abgeschlossen ist
     */
    public function finalizeStudent(GradingDocumentationSession $session, Schueler $schueler, User $user): bool
    {
        $now = now();

        if ($session->isIndividualSession()) {
            $session->update(['completed_at' => $now]);

            return true;
        }

        // Session ohne Teilnehmerliste (ganze Klasse): Liste festschreiben, bevor einzelne Schüler abgeschlossen werden
        if (!$session->participants()->exists()) {
            $this->addParticipants($session, $this->participants($session)->pluck('id')->all());
        }

        GradingSessionStudent::updateOrCreate(
            ['session_id' => $session->id, 'schueler_id' => $schueler->id],
            ['finalized_at' => $now, 'finalized_by' => $user->id]
        );

        $open = $session->participants()->whereNull('finalized_at')->exists();
        if (!$open) {
            $session->update(['completed_at' => $now]);
        }

        return !$open;
    }

    /**
     * Fortschritt: answered = Schüler-Frage-Paare mit Pädagogenbewertung (rating_value),
     * total = Teilnehmer × aktive Fragen des Graduierungssystems.
     */
    public function progress(GradingDocumentationSession $session): array
    {
        $studentIds = $this->participants($session)->pluck('id');
        $questionIds = GradingQuestion::where('grading_system_id', $session->grading_system_id)
            ->where('active', true)
            ->pluck('id');

        $answered = $studentIds->isEmpty() || $questionIds->isEmpty() ? 0 : GradingTeacherAssessment::where('session_id', $session->id)
            ->whereIn('schueler_id', $studentIds)
            ->whereIn('question_id', $questionIds)
            ->whereNotNull('teacher_rating')
            ->count();

        return [
            'answered' => $answered,
            'total' => $studentIds->count() * $questionIds->count(),
            'students_total' => $studentIds->count(),
            'students_finalized' => $this->finalizedIds($session)->intersect($studentIds->map(fn ($id) => (int) $id))->count(),
        ];
    }

    /**
     * Aktive Fragen der Session; im Modus "by_question" nur bis einschließlich der freigegebenen Frage.
     */
    public function releasedQuestions(GradingDocumentationSession $session): Collection
    {
        $questions = GradingQuestion::where('grading_system_id', $session->grading_system_id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if (!$session->usesQuestionOrder()) {
            return $questions;
        }

        $index = $questions->search(fn ($q) => (int) $q->id === (int) $session->current_question_id);

        return $index === false ? collect() : $questions->take($index + 1)->values();
    }

    private function addParticipants(GradingDocumentationSession $session, array $schuelerIds): void
    {
        $existing = $session->participants()->pluck('schueler_id')->map(fn ($id) => (int) $id);

        collect($schuelerIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->diff($existing)
            ->each(fn ($id) => GradingSessionStudent::create(['session_id' => $session->id, 'schueler_id' => $id]));
    }
}
