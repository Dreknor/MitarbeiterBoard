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
            $this->zugaengeEntfernen($session);

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
            $this->zugaengeEntfernen($session);
        }

        return !$open;
    }

    /**
     * Schließt die ganze Session ab (Web „Abschließen“). Wie beim Abschluss über die API erhalten
     * vorhandene Teilnehmer ohne Abschluss finalized_at/finalized_by; QR-Zugänge werden entfernt.
     */
    public function abschliessen(GradingDocumentationSession $session, User $user): void
    {
        DB::transaction(function () use ($session, $user) {
            $now = now();

            $session->participants()->whereNull('finalized_at')->update([
                'finalized_at' => $now,
                'finalized_by' => $user->id,
            ]);
            $session->update(['completed_at' => $now]);
            $this->zugaengeEntfernen($session);
        });
    }

    /**
     * Prüft, ob für den Schüler (und optional die Frage) in der Session noch geschrieben werden darf.
     * Gemeinsam genutzt von den Web-Endpunkten (Lehrkraft und öffentlicher QR-Zugang).
     *
     * @return array{0: int, 1: string}|null  [HTTP-Status, Meldung] oder null, wenn erlaubt
     */
    public function schreibFehler(GradingDocumentationSession $session, int $schuelerId, ?int $questionId = null): ?array
    {
        if ($session->isCompleted()) {
            return [409, 'Die Session ist abgeschlossen. Öffne sie wieder, um Änderungen vorzunehmen.'];
        }

        if (!$this->isParticipant($session, $schuelerId)) {
            return [422, 'Der Schüler nimmt nicht an dieser Session teil.'];
        }

        if ($this->isFinalized($session, $schuelerId)) {
            return [409, 'Die Bewertung dieses Schülers ist in der Session bereits abgeschlossen.'];
        }

        if ($questionId !== null && !GradingQuestion::whereKey($questionId)
            ->where('grading_system_id', $session->grading_system_id)
            ->where('active', true)
            ->exists()) {
            return [422, 'Die Frage gehört nicht zum Graduierungssystem dieser Session.'];
        }

        return null;
    }

    /**
     * Öffentliche QR-Zugänge der Session ungültig machen (bei Abschluss oder Abbruch).
     */
    public function zugaengeEntfernen(GradingDocumentationSession $session): void
    {
        DB::table('grading_student_tokens')->where('session_id', $session->id)->delete();
    }

    /**
     * Öffnet eine abgeschlossene Session wieder. Auch die Abschlüsse einzelner Schüler
     * (finalized_at, z. B. per App gesetzt) werden zurückgesetzt, sonst blieb die Session
     * nach dem Wiederöffnen in der App für alle Schüler gesperrt (409).
     */
    public function wiederoeffnen(GradingDocumentationSession $session): bool
    {
        return DB::transaction(function () use ($session) {
            if (!$session->reopen()) {
                return false;
            }

            $session->participants()->update(['finalized_at' => null, 'finalized_by' => null]);

            return true;
        });
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
     * Fortschritt mehrerer Sessions (wie progress()), aber mit einer festen Anzahl Abfragen
     * statt ~4 je Session – für Listen (API classSessions).
     *
     * @param  iterable<GradingDocumentationSession>  $sessions
     * @return array<int, array>  Fortschritt je Session-ID
     */
    public function progressFuer(iterable $sessions): array
    {
        $sessions = collect($sessions);
        if ($sessions->isEmpty()) {
            return [];
        }

        $sessionIds = $sessions->pluck('id');

        // Teilnehmerlisten (grading_session_students) aller Sessions
        $teilnehmer = GradingSessionStudent::whereIn('session_id', $sessionIds)
            ->get(['session_id', 'schueler_id', 'finalized_at'])
            ->groupBy('session_id');

        // Schüler bestimmen, die tatsächlich existieren (wie participants())
        $gruppeOhneListe = $sessions->filter(fn ($s) => !$s->isIndividualSession() && !$teilnehmer->has($s->id));
        $klassenSchueler = $gruppeOhneListe->isEmpty() ? collect() : Schueler::whereIn('klasse_id', $gruppeOhneListe->pluck('klasse_id')->unique())
            ->get(['id', 'klasse_id'])
            ->groupBy('klasse_id')
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id));

        $einzelIds = $teilnehmer->flatten(1)->pluck('schueler_id')
            ->merge($sessions->filter(fn ($s) => $s->isIndividualSession())->pluck('schueler_id'))
            ->filter()
            ->unique();
        $vorhanden = $einzelIds->isEmpty() ? collect() : Schueler::whereIn('id', $einzelIds)->pluck('id')->map(fn ($id) => (int) $id)->flip();

        $fragen = GradingQuestion::whereIn('grading_system_id', $sessions->pluck('grading_system_id')->unique())
            ->where('active', true)
            ->get(['id', 'grading_system_id'])
            ->groupBy('grading_system_id')
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id));

        $bewertungen = GradingTeacherAssessment::whereIn('session_id', $sessionIds)
            ->whereNotNull('teacher_rating')
            ->get(['session_id', 'schueler_id', 'question_id'])
            ->groupBy('session_id');

        $ergebnis = [];
        foreach ($sessions as $session) {
            $liste = $teilnehmer->get($session->id, collect());

            if ($session->isIndividualSession()) {
                $studentIds = collect([(int) $session->schueler_id])->filter(fn ($id) => $vorhanden->has($id));
            } elseif ($liste->isNotEmpty()) {
                $studentIds = $liste->pluck('schueler_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $vorhanden->has($id))->unique();
            } else {
                $studentIds = $klassenSchueler->get($session->klasse_id, collect());
            }

            $questionIds = $fragen->get($session->grading_system_id, collect());
            $schuelerSet = $studentIds->flip();
            $fragenSet = $questionIds->flip();

            $answered = $studentIds->isEmpty() || $questionIds->isEmpty() ? 0 : $bewertungen->get($session->id, collect())
                ->filter(fn ($a) => $schuelerSet->has((int) $a->schueler_id) && $fragenSet->has((int) $a->question_id))
                ->count();

            $finalized = $session->isCompleted()
                ? $studentIds->count()
                : $liste->whereNotNull('finalized_at')->pluck('schueler_id')->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $schuelerSet->has($id))->unique()->count();

            $ergebnis[$session->id] = [
                'answered' => $answered,
                'total' => $studentIds->count() * $questionIds->count(),
                'students_total' => $studentIds->count(),
                'students_finalized' => $finalized,
            ];
        }

        return $ergebnis;
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
