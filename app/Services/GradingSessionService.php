<?php

namespace App\Services;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingSessionStage;
use App\Models\GradingSessionStudent;
use App\Models\GradingStudentAnswer;
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

        // Stufe festschreiben, bevor sie z. B. beim Abschluss geändert wird
        $this->stufeFestschreiben($session, (int) $schueler->id);

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

            $this->stufenFestschreiben($session, $this->participants($session)->pluck('id')->all());

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

        if ($questionId !== null && !$this->fragenFuerSchueler($session, $schuelerId)->contains($questionId)) {
            return [422, 'Die Frage gilt nicht für die Graduierungsstufe dieses Schülers.'];
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
     * total = Summe der Fragen je Teilnehmer (nur Fragen seiner Graduierungsstufe).
     */
    public function progress(GradingDocumentationSession $session): array
    {
        return $this->progressFuer([$session])[$session->id];
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

        $bewertungen = GradingTeacherAssessment::whereIn('session_id', $sessionIds)
            ->whereNotNull('teacher_rating')
            ->get(['session_id', 'schueler_id', 'question_id'])
            ->groupBy('session_id');

        $schuelerJeSession = [];
        foreach ($sessions as $session) {
            $liste = $teilnehmer->get($session->id, collect());

            if ($session->isIndividualSession()) {
                $studentIds = collect([(int) $session->schueler_id])->filter(fn ($id) => $vorhanden->has($id));
            } elseif ($liste->isNotEmpty()) {
                $studentIds = $liste->pluck('schueler_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $vorhanden->has($id))->unique();
            } else {
                $studentIds = $klassenSchueler->get($session->klasse_id, collect());
            }

            $schuelerJeSession[$session->id] = $studentIds->values();
        }

        // Fragen je Schüler (nach Graduierungsstufe) aller Sessions mit fester Anzahl Abfragen
        $fragenJeSession = $this->fragenJeSchuelerFuer($sessions, $schuelerJeSession);

        $ergebnis = [];
        foreach ($sessions as $session) {
            $liste = $teilnehmer->get($session->id, collect());
            $studentIds = $schuelerJeSession[$session->id];
            $schuelerSet = $studentIds->flip();
            $fragenJeSchueler = $fragenJeSession[$session->id] ?? collect();

            $answered = $bewertungen->get($session->id, collect())
                ->filter(fn ($a) => $schuelerSet->has((int) $a->schueler_id)
                    && ($fragenJeSchueler->get((int) $a->schueler_id) ?? collect())->contains((int) $a->question_id))
                ->count();

            $finalized = $session->isCompleted()
                ? $studentIds->count()
                : $liste->whereNotNull('finalized_at')->pluck('schueler_id')->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $schuelerSet->has($id))->unique()->count();

            $ergebnis[$session->id] = [
                'answered' => $answered,
                'total' => $studentIds->sum(fn ($id) => ($fragenJeSchueler->get($id) ?? collect())->count()),
                'students_total' => $studentIds->count(),
                'students_finalized' => $finalized,
            ];
        }

        return $ergebnis;
    }

    /**
     * Aktive Fragen der Session; im Modus "by_question" nur bis einschließlich der freigegebenen Frage.
     * Mit Schüler: nur die Fragen seiner Graduierungsstufe (Fragen anderer Stufen werden übersprungen).
     */
    public function releasedQuestions(GradingDocumentationSession $session, ?int $schuelerId = null): Collection
    {
        $questions = GradingQuestion::where('grading_system_id', $session->grading_system_id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($session->usesQuestionOrder()) {
            $index = $questions->search(fn ($q) => (int) $q->id === (int) $session->current_question_id);
            $questions = $index === false ? collect() : $questions->take($index + 1)->values();
        }

        if ($schuelerId === null) {
            return $questions;
        }

        $erlaubt = $this->fragenFuerSchueler($session, $schuelerId);

        return $questions->filter(fn ($q) => $erlaubt->contains((int) $q->id))->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Fragen je Graduierungsstufe
    |--------------------------------------------------------------------------
    | Eine Frage gilt für die ihr zugeordneten Stufen, ohne Zuordnung für alle Stufen.
    | Maßgeblich ist die in der Session festgeschriebene Stufe (grading_session_stages), sonst die
    | aktuelle Stufe des Schülers. Festgeschrieben wird beim ersten Beantworten/Bewerten und beim
    | Abschluss – bis dahin folgt die Session einer korrigierten Stufe.
    | Bereits beantwortete/bewertete Fragen bleiben immer sichtbar. Abgeschlossene Sessions ohne
    | festgeschriebene Stufe stammen aus der Zeit vor der Stufenbindung (jeder Abschluss schreibt die
    | Stufe fest) – damals galten alle Fragen, daher dort keine Filterung nach Stufe.
    */

    /**
     * Stufe je Schüler in der Session: ['stage_id' => ?int, 'fest' => bool].
     *
     * @param  iterable<int>  $schuelerIds
     */
    public function stufen(GradingDocumentationSession $session, iterable $schuelerIds): Collection
    {
        $ids = collect($schuelerIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $fest = GradingSessionStage::where('session_id', $session->id)->whereIn('schueler_id', $ids)
            ->pluck('grading_stage_id', 'schueler_id');
        $aktuell = Schueler::whereIn('id', $ids)->pluck('grading_stage_id', 'id');

        return $ids->mapWithKeys(fn ($id) => [$id => [
            'stage_id' => $fest->has($id)
                ? ($fest[$id] !== null ? (int) $fest[$id] : null)
                : (isset($aktuell[$id]) ? (int) $aktuell[$id] : null),
            'fest' => $fest->has($id),
        ]]);
    }

    /**
     * Fragen-IDs je Schüler (sortiert wie im Graduierungssystem).
     *
     * @param  iterable<int>|null  $schuelerIds  null = alle Teilnehmer
     * @return Collection<int, Collection<int, int>>
     */
    public function fragenJeSchueler(GradingDocumentationSession $session, ?iterable $schuelerIds = null): Collection
    {
        $ids = collect($schuelerIds ?? $this->participants($session)->pluck('id'))->map(fn ($id) => (int) $id)->unique()->values();

        return $this->fragenJeSchuelerFuer(collect([$session]), [$session->id => $ids])[$session->id] ?? collect();
    }

    /** Fragen-IDs eines Schülers in der Session */
    public function fragenFuerSchueler(GradingDocumentationSession $session, int $schuelerId): Collection
    {
        return $this->fragenJeSchueler($session, [$schuelerId])->get($schuelerId, collect());
    }

    /**
     * Aktive Fragen, die mindestens einen der Schüler betreffen (sortiert), z. B. für Ansichten
     * einer Gruppensession.
     */
    public function sessionFragen(GradingDocumentationSession $session, Collection $fragenJeSchueler): Collection
    {
        $ids = $fragenJeSchueler->flatten()->map(fn ($id) => (int) $id)->unique()->flip();

        return GradingQuestion::where('grading_system_id', $session->grading_system_id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn ($q) => $ids->has((int) $q->id))
            ->values();
    }

    /**
     * Beschränkt die (geladenen) Fragen des Graduierungssystems je Session auf die Fragen des
     * Schülers – für Verlauf, Schülerakte, Dossier und Export. Jede Session erhält dafür eine eigene
     * Kopie von gradingSystem (beim Eager Loading teilen sich Sessions sonst dieselbe Instanz).
     *
     * @param  Collection<GradingDocumentationSession>  $sessions
     */
    public function fragenAufSchuelerBeschraenken(Collection $sessions, int $schuelerId): Collection
    {
        $fragenJeSession = $this->fragenJeSchuelerFuer(
            $sessions,
            $sessions->mapWithKeys(fn ($s) => [$s->id => collect([$schuelerId])])->all()
        );

        foreach ($sessions as $session) {
            $system = $session->gradingSystem;
            if (!$system || !$system->relationLoaded('questions')) {
                continue;
            }

            $ids = ($fragenJeSession[$session->id] ?? collect())->get($schuelerId, collect())->flip();
            $kopie = clone $system;
            $kopie->setRelation('questions', $system->questions->filter(fn ($q) => $ids->has((int) $q->id))->values());
            $session->setRelation('gradingSystem', $kopie);
        }

        return $sessions;
    }

    /**
     * Schreibt die aktuelle Stufe des Schülers für die Session fest, falls noch nicht geschehen.
     */
    public function stufeFestschreiben(GradingDocumentationSession $session, int $schuelerId): void
    {
        $this->stufenFestschreiben($session, [$schuelerId]);
    }

    /** @param  int[]  $schuelerIds */
    public function stufenFestschreiben(GradingDocumentationSession $session, array $schuelerIds): void
    {
        $ids = collect($schuelerIds)->map(fn ($id) => (int) $id)->unique();
        if ($ids->isEmpty()) {
            return;
        }

        $vorhanden = GradingSessionStage::where('session_id', $session->id)->whereIn('schueler_id', $ids)->pluck('schueler_id')
            ->map(fn ($id) => (int) $id);
        $fehlend = $ids->diff($vorhanden);
        if ($fehlend->isEmpty()) {
            return;
        }

        $aktuell = Schueler::whereIn('id', $fehlend)->pluck('grading_stage_id', 'id');
        foreach ($aktuell as $schuelerId => $stageId) {
            GradingSessionStage::firstOrCreate(
                ['session_id' => $session->id, 'schueler_id' => (int) $schuelerId],
                ['grading_stage_id' => $stageId]
            );
        }
    }

    /**
     * Kern: Fragen-IDs je Session und Schüler mit fester Anzahl Abfragen.
     *
     * @param  Collection<GradingDocumentationSession>  $sessions
     * @param  array<int, Collection<int, int>>  $schuelerJeSession
     * @return array<int, Collection<int, Collection<int, int>>>
     */
    private function fragenJeSchuelerFuer(Collection $sessions, array $schuelerJeSession): array
    {
        $alleSchueler = collect($schuelerJeSession)->flatten()->map(fn ($id) => (int) $id)->unique()->values();
        if ($sessions->isEmpty() || $alleSchueler->isEmpty()) {
            return $sessions->mapWithKeys(fn ($s) => [$s->id => collect()])->all();
        }

        $sessionIds = $sessions->pluck('id');

        // Alle Fragen der Systeme (auch inaktive – für bereits beantwortete) in Systemreihenfolge
        $fragen = GradingQuestion::whereIn('grading_system_id', $sessions->pluck('grading_system_id')->unique())
            ->with('stages:id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('grading_system_id');

        $fest = GradingSessionStage::whereIn('session_id', $sessionIds)->whereIn('schueler_id', $alleSchueler)
            ->get(['session_id', 'schueler_id', 'grading_stage_id'])
            ->keyBy(fn ($r) => $r->session_id . '-' . $r->schueler_id);
        $aktuell = Schueler::whereIn('id', $alleSchueler)->pluck('grading_stage_id', 'id');

        $beantwortet = GradingStudentAnswer::whereIn('session_id', $sessionIds)->whereIn('schueler_id', $alleSchueler)
            ->whereNotNull('self_rating')
            ->get(['session_id', 'schueler_id', 'question_id'])
            ->concat(GradingTeacherAssessment::whereIn('session_id', $sessionIds)->whereIn('schueler_id', $alleSchueler)
                ->where(fn ($q) => $q->whereNotNull('teacher_rating')->orWhereNotNull('comment'))
                ->get(['session_id', 'schueler_id', 'question_id']))
            ->groupBy(fn ($r) => $r->session_id . '-' . $r->schueler_id)
            ->map(fn ($rows) => $rows->pluck('question_id')->map(fn ($id) => (int) $id)->unique()->flip());

        $ergebnis = [];
        foreach ($sessions as $session) {
            $systemFragen = $fragen->get($session->grading_system_id, collect());
            $ergebnis[$session->id] = collect($schuelerJeSession[$session->id] ?? [])
                ->mapWithKeys(function ($schuelerId) use ($session, $systemFragen, $fest, $aktuell, $beantwortet) {
                    $schuelerId = (int) $schuelerId;
                    $key = $session->id . '-' . $schuelerId;
                    $erledigt = $beantwortet->get($key, collect());
                    $zeile = $fest->get($key);

                    // Abgeschlossen ohne festgeschriebene Stufe (Altbestand): alle Fragen galten
                    $altbestand = $session->isCompleted() && !$zeile;
                    $stageId = $zeile
                        ? ($zeile->grading_stage_id !== null ? (int) $zeile->grading_stage_id : null)
                        : (isset($aktuell[$schuelerId]) ? (int) $aktuell[$schuelerId] : null);

                    $ids = $systemFragen
                        ->filter(fn ($q) => $erledigt->has((int) $q->id)
                            || ($q->active && ($altbestand || $q->giltFuerStufe($stageId))))
                        ->map(fn ($q) => (int) $q->id)
                        ->values();

                    return [$schuelerId => $ids];
                });
        }

        return $ergebnis;
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
