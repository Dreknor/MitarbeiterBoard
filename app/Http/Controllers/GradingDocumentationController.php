<?php

namespace App\Http\Controllers;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingStudentAnswer;
use App\Models\GradingTeacherAssessment;
use App\Models\GradingCoachingNote;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\PaedDiaryClassGroup;
use App\Services\GradingSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradingDocumentationController extends Controller
{
    /**
     * Zeigt die Hauptansicht für die Dokumentation
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $klassen = $user->paed_klassen()->with('gradingSystem')->orderBy('name')->get();

        if ($klassen->isEmpty()) {
            return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Keine Klassen zugewiesen.']);
        }

        $groups = Auth::user()->paed_diary_class_groups()->with('klassen:id,name')->get();

        // Offene Sessions des Benutzers laden
        $openSessions = GradingDocumentationSession::where('user_id', $user->id)
            ->whereNull('completed_at')
            ->with(['klasse', 'gradingSystem', 'schueler', 'group'])
            ->orderBy('started_at', 'desc')
            ->get();

        // Abgeschlossene Sessions im aktuellen Schuljahr
        $completedSessions = GradingDocumentationSession::where('user_id', $user->id)
            ->completed()
            ->currentSchoolYear()
            ->with(['klasse', 'gradingSystem', 'schueler', 'group'])
            ->orderBy('completed_at', 'desc')
            ->get();

        // Maximale Wiederöffnungsfrist aus Settings
        $reopenDays = settings('session_reopen_days', 'config.grading_documentation');

        return view($this->neueAnsicht() ? 'paedDiary.neu.graduierung.index' : 'paedDiary.documentation.index', [
            'klassen' => $klassen,
            'groups' => $groups,
            'openSessions' => $openSessions,
            'completedSessions' => $completedSessions,
            'reopenDays' => $reopenDays,
        ]);
    }

    /**
     * Startet eine neue Gruppen-Dokumentationssession
     */
    public function startGroupSession(Request $request)
    {
        $request->validate([
            'klasse_id' => 'required|exists:klassen,id',
            'group_id' => 'nullable|exists:paed_diary_class_groups,id',
            'answer_order_mode' => 'nullable|in:' . implode(',', GradingDocumentationSession::ANSWER_ORDER_MODES),
        ]);

        $user = Auth::user();
        $klasse = $user->paed_klassen()->findOrFail($request->klasse_id);
        $answerOrderMode = GradingDocumentationSession::normalizeAnswerOrderMode($request->input('answer_order_mode'));

        if (!$klasse->gradingSystem) {
            return response()->json(['message' => 'Dieser Klasse ist kein Graduierungssystem zugeordnet.'], 422);
        }

        // Lerngruppe muss eine eigene sein und die Klasse enthalten (wie in der API)
        if ($request->filled('group_id')) {
            $gruppeGueltig = $user->paed_diary_class_groups()
                ->whereKey($request->integer('group_id'))
                ->whereHas('klassen', fn ($q) => $q->where('klassen.id', $klasse->id))
                ->exists();

            if (!$gruppeGueltig) {
                return response()->json([
                    'message' => 'Die Lerngruppe gehört nicht zu dir oder enthält die Klasse nicht.',
                    'errors' => ['group_id' => ['Die Lerngruppe gehört nicht zu dir oder enthält die Klasse nicht.']],
                ], 422);
            }
        }

        // Offene eigene Session für Klasse/Gruppe fortsetzen oder neu anlegen (gemeinsame Logik mit der API)
        [$session, $resumed] = app(GradingSessionService::class)
            ->startGroupSession($user, $klasse, $request->filled('group_id') ? $request->integer('group_id') : null, $answerOrderMode);

        return response()->json([
            'session' => $session,
            'redirect' => route('gradingDocumentation.groupSession', $session->id),
            'resumed' => $resumed
        ]);
    }

    /**
     * Zeigt die Gruppen-Dokumentationssession
     */
    public function showGroupSession(GradingDocumentationSession $session)
    {
        $this->authorize('view', $session);

        $session->load([
            'klasse',
            'gradingSystem.questions' => function($q) {
                $q->where('active', true)->orderBy('sort_order');
            },
            'studentAnswers'
        ]);

        // Teilnehmer der Session (Teilmenge aus der App oder ganze Klasse)
        $schueler = $this->sessions()->participants($session);

        if ($this->neueAnsicht()) {
            return view('paedDiary.neu.graduierung.gruppe', [
                'session' => $session,
                'schueler' => $schueler,
                'questions' => $session->gradingSystem->questions,
                'finalisiert' => $this->sessions()->finalizedIds($session)->values(),
            ]);
        }

        return view('paedDiary.documentation.group-session', [
            'session' => $session,
            'schueler' => $schueler,
            'questions' => $session->gradingSystem->questions,
        ]);
    }

    /**
     * Speichert eine Schülerantwort während einer Gruppen-Session
     */
    public function saveStudentAnswer(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:grading_documentation_sessions,id',
            'schueler_id' => 'required|exists:schueler,id',
            'question_id' => 'required|exists:grading_questions,id',
            'self_rating' => 'required|integer|min:1|max:5',
        ]);

        $session = GradingDocumentationSession::findOrFail($request->session_id);
        $this->authorize('update', $session);
        if ($fehler = $this->sessions()->schreibFehler($session, (int) $request->schueler_id, (int) $request->question_id)) {
            return response()->json(['message' => $fehler[1]], $fehler[0]);
        }

        $answer = GradingStudentAnswer::updateOrCreate(
            [
                'session_id' => $request->session_id,
                'schueler_id' => $request->schueler_id,
                'question_id' => $request->question_id,
            ],
            [
                'self_rating' => $request->self_rating,
                'answered_at' => now(),
            ]
        );

        return response()->json(['answer' => $answer]);
    }

    /**
     * Zeigt die Lehrereinschätzungs-Ansicht für eine Session
     */
    public function showTeacherAssessment(GradingDocumentationSession $session)
    {
        $this->authorize('view', $session);

        $session->load([
            'gradingSystem.questions' => function($q) {
                $q->where('active', true)->orderBy('sort_order');
            },
            'studentAnswers',
            'teacherAssessments',
            'coachingNotes'
        ]);

        $session->load(['klasse', 'schueler']);

        // Teilnehmer (individuell: der Schüler, Gruppe: Teilnehmerliste oder ganze Klasse)
        $schueler = $this->sessions()->participants($session);

        // Neue Ansicht: wahlweise Matrix (Schüler × Fragen) statt „Schüler für Schüler“
        $darstellung = Auth::user()->paedDiaryDarstellung();
        if ($darstellung['ansicht'] === 'neu' && $darstellung['bewertung'] === 'matrix') {
            $finalisiert = $this->sessions()->finalizedIds($session)->all();

            return view('paedDiary.neu.bewertung-matrix', [
                'session' => $session,
                'schueler' => $schueler,
                'questions' => $session->gradingSystem->questions,
                'reopenDays' => (int) settings('session_reopen_days', 'config.grading_documentation'),
                'matrix' => $schueler->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => trim($s->vorname . ' ' . $s->nachname),
                    'abgeschlossen' => in_array((int) $s->id, $finalisiert, true),
                    'selbst' => $session->studentAnswers->where('schueler_id', $s->id)->pluck('self_rating', 'question_id'),
                    'lehrkraft' => $session->teacherAssessments->where('schueler_id', $s->id)->pluck('teacher_rating', 'question_id'),
                    'kommentare' => $session->teacherAssessments->where('schueler_id', $s->id)->pluck('comment', 'question_id'),
                ])->values(),
            ]);
        }

        // Neue Ansicht, Modus „Schüler für Schüler“: eine Schülerin/ein Schüler nach dem anderen
        if ($darstellung['ansicht'] === 'neu') {
            $finalisiert = $this->sessions()->finalizedIds($session)->all();

            return view('paedDiary.neu.graduierung.bewertung', [
                'session' => $session,
                'schueler' => $schueler,
                'questions' => $session->gradingSystem->questions,
                'reopenDays' => (int) settings('session_reopen_days', 'config.grading_documentation'),
                'daten' => $schueler->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => trim($s->vorname . ' ' . $s->nachname),
                    'abgeschlossen' => in_array((int) $s->id, $finalisiert, true),
                    'selbst' => $session->studentAnswers->where('schueler_id', $s->id)->pluck('self_rating', 'question_id'),
                    'lehrkraft' => $session->teacherAssessments->where('schueler_id', $s->id)->pluck('teacher_rating', 'question_id'),
                    'kommentare' => $session->teacherAssessments->where('schueler_id', $s->id)->pluck('comment', 'question_id'),
                    'notiz' => (string) $session->coachingNotes->firstWhere('schueler_id', $s->id)?->note,
                ])->values(),
            ]);
        }

        return view('paedDiary.documentation.teacher-assessment', [
            'darstellung' => $darstellung,
            'session' => $session,
            'schueler' => $schueler,
            'questions' => $session->gradingSystem->questions,
            'reopenDays' => (int) settings('session_reopen_days', 'config.grading_documentation'),
        ]);
    }

    /**
     * Speichert eine Lehrereinschätzung
     */
    public function saveTeacherAssessment(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:grading_documentation_sessions,id',
            'schueler_id' => 'required|exists:schueler,id',
            'question_id' => 'required|exists:grading_questions,id',
            'teacher_rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $session = GradingDocumentationSession::findOrFail($request->session_id);
        $this->authorize('update', $session);
        if ($fehler = $this->sessions()->schreibFehler($session, (int) $request->schueler_id, (int) $request->question_id)) {
            return response()->json(['message' => $fehler[1]], $fehler[0]);
        }

        $assessment = GradingTeacherAssessment::updateOrCreate(
            [
                'session_id' => $request->session_id,
                'schueler_id' => $request->schueler_id,
                'question_id' => $request->question_id,
            ],
            [
                'teacher_rating' => $request->teacher_rating,
                'comment' => $request->comment,
                'assessed_at' => now(),
            ]
        );

        return response()->json(['assessment' => $assessment]);
    }

    /**
     * Speichert das kurze Coaching-Protokoll für einen Schüler innerhalb einer Session
     */
    public function saveCoachingNote(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:grading_documentation_sessions,id',
            'schueler_id' => 'required|exists:schueler,id',
            'note' => 'nullable|string|max:5000',
        ]);

        $session = GradingDocumentationSession::findOrFail($request->session_id);
        $this->authorize('update', $session);
        if ($fehler = $this->sessions()->schreibFehler($session, (int) $request->schueler_id)) {
            return response()->json(['message' => $fehler[1]], $fehler[0]);
        }

        $note = GradingCoachingNote::updateOrCreate(
            [
                'session_id' => $request->session_id,
                'schueler_id' => $request->schueler_id,
            ],
            [
                'user_id' => Auth::id(),
                'note' => $request->note,
                'noted_at' => now(),
            ]
        );

        return response()->json(['note' => $note]);
    }

    /**
     * Schließt eine Dokumentationssession ab
     */
    public function completeSession(GradingDocumentationSession $session)
    {
        $this->authorize('update', $session);

        // Erneuter Abschluss würde completed_at überschreiben und die Frist zum Wiederöffnen verlängern
        if ($session->isCompleted()) {
            return response()->json(['message' => 'Die Session ist bereits abgeschlossen.'], 409);
        }

        $this->sessions()->abschliessen($session, Auth::user());

        return response()->json(['message' => 'Session erfolgreich abgeschlossen.']);
    }

    /**
     * Bricht eine offene Session ab und löscht sie
     */
    public function cancelSession(GradingDocumentationSession $session)
    {
        $this->authorize('update', $session);

        // Nur offene Sessions können abgebrochen werden
        if ($session->isCompleted()) {
            return response()->json(['message' => 'Abgeschlossene Sessions können nicht gelöscht werden.'], 422);
        }

        // Alle zugehörigen Daten löschen
        $this->sessions()->zugaengeEntfernen($session);
        $session->studentAnswers()->delete();
        $session->teacherAssessments()->delete();
        $session->delete();

        return response()->json(['message' => 'Session erfolgreich abgebrochen.']);
    }

    /**
     * Startet eine individuelle Dokumentationssession
     */
    public function startIndividualSession(Request $request)
    {
        $request->validate([
            'klasse_id' => 'required|exists:klassen,id',
            'schueler_id' => 'required|exists:schueler,id',
        ]);

        $user = Auth::user();
        $klasse = $user->paed_klassen()->findOrFail($request->klasse_id);

        // Der Schüler muss zur gewählten Klasse gehören (sonst Zugriff auf fremde Schüler per ID)
        if (!$klasse->schueler()->whereKey($request->schueler_id)->exists()) {
            return response()->json(['message' => 'Der Schüler gehört nicht zu dieser Klasse.'], 422);
        }

        if (!$klasse->gradingSystem) {
            return response()->json(['message' => 'Dieser Klasse ist kein Graduierungssystem zugeordnet.'], 422);
        }

        // Offene eigene Session für den Schüler fortsetzen oder neu anlegen (gemeinsame Logik mit der API)
        [$session, $resumed] = app(GradingSessionService::class)
            ->startIndividualSession($user, $klasse, (int) $request->schueler_id);

        return response()->json([
            'session' => $session,
            'redirect' => route('gradingDocumentation.individualSession', $session->id),
            'resumed' => $resumed
        ]);
    }

    /**
     * Zeigt die individuelle Dokumentationssession
     */
    public function showIndividualSession(GradingDocumentationSession $session)
    {
        $this->authorize('view', $session);

        $session->load([
            'schueler',
            'klasse',
            'gradingSystem.questions' => function($q) {
                $q->where('active', true)->orderBy('sort_order');
            },
            'studentAnswers',
            'teacherAssessments'
        ]);

        if ($this->neueAnsicht()) {
            return view('paedDiary.neu.graduierung.einzel', [
                'session' => $session,
                'schueler' => $session->schueler,
                'questions' => $session->gradingSystem->questions,
            ]);
        }

        return view('paedDiary.documentation.individual-session', [
            'session' => $session,
            'schueler' => $session->schueler,
            'questions' => $session->gradingSystem->questions,
        ]);
    }

    /**
     * Zeigt die Dokumentationen eines Schülers in der Schüleransicht
     */
    public function showSchuelerDocumentations(Schueler $schueler)
    {
        $user = Auth::user();

        // Prüfen ob Zugriff auf Schüler besteht
        $klasse = $schueler->klasse;
        if (!$user->paed_klassen()->where('klassen.id', $klasse->id)->exists()) {
            abort(403);
        }

        // Gruppen-Sitzungen sind klassenspezifisch, individuelle Sitzungen dürfen NICHT
        // zusätzlich auf die aktuelle klasse_id des Schülers eingeschränkt werden - sonst
        // gehen individuelle Sitzungen aus einer früheren Klasse (Schuljahreswechsel) verloren.
        $sessions = GradingDocumentationSession::where(function($q) use ($klasse, $schueler) {
                $q->where(function($q2) use ($klasse) {
                    $q2->where('type', 'group')->where('klasse_id', $klasse->id);
                })->orWhere(function($q2) use ($schueler) {
                    $q2->where('type', 'individual')->where('schueler_id', $schueler->id);
                });
            })
            ->whereNotNull('completed_at')
            ->with([
                'gradingSystem',
                'user',
                'studentAnswers' => function($q) use ($schueler) {
                    $q->where('schueler_id', $schueler->id);
                },
                'teacherAssessments' => function($q) use ($schueler) {
                    $q->where('schueler_id', $schueler->id);
                },
                'coachingNotes' => function($q) use ($schueler) {
                    $q->where('schueler_id', $schueler->id);
                }
            ])
            ->orderBy('completed_at', 'desc')
            ->get();

        if ($this->neueAnsicht()) {
            // Fragen je System einmal laden (statt einer Abfrage je Session in der View)
            $sessions->load(['gradingSystem.questions' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

            return view('paedDiary.neu.graduierung.schueler', [
                'schueler' => $schueler,
                'klasse' => $klasse,
                'sessions' => $sessions,
            ]);
        }

        return view('paedDiary.documentation.schueler-overview', [
            'schueler' => $schueler,
            'klasse' => $klasse,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Holt die Daten einer Session (für AJAX)
     */
    public function getSessionData(GradingDocumentationSession $session)
    {
        $this->authorize('view', $session);

        $session->load([
            'studentAnswers.question',
            'teacherAssessments.question'
        ]);

        // Nur Antworten/Bewertungen der Teilnehmer liefern (wie in der API)
        $ids = $this->sessions()->participants($session)->pluck('id')->map(fn ($id) => (int) $id);
        $session->setRelation('studentAnswers', $session->studentAnswers->filter(fn ($a) => $ids->contains((int) $a->schueler_id))->values());
        $session->setRelation('teacherAssessments', $session->teacherAssessments->filter(fn ($a) => $ids->contains((int) $a->schueler_id))->values());

        return response()->json([
            'session' => $session,
            'schueler_ids' => $ids->values(),
            'finalized_ids' => $this->sessions()->finalizedIds($session)->values(),
        ]);
    }

    /**
     * Generiert einen temporären Token für QR-Code Zugriff auf Schüler-Dokumentation
     */
    public function generateStudentQRToken(GradingDocumentationSession $session, Schueler $schueler)
    {
        // Autorisierung außerhalb des try-Blocks: 403 statt verschluckter Ausnahme
        $this->authorize('update', $session);

        if ($session->isCompleted()) {
            return response()->json(['message' => 'Die Session ist abgeschlossen.'], 409);
        }

        // Schüler muss an der Session teilnehmen (individuell: der Schüler, Gruppe: Teilnehmer/Klasse)
        if (!$this->sessions()->isParticipant($session, (int) $schueler->id)) {
            return response()->json([
                'message' => 'Der Schüler nimmt nicht an dieser Session teil.'
            ], 403);
        }

        try {
            // Noch gültigen Token für Session+Schüler wiederverwenden (sonst entstünden bei jedem Klick
            // weitere gültige Zugänge); erst kurz vor Ablauf einen neuen erzeugen.
            $vorhanden = DB::table('grading_student_tokens')
                ->where('session_id', $session->id)
                ->where('schueler_id', $schueler->id)
                ->where('expires_at', '>', now()->addHour())
                ->orderByDesc('expires_at')
                ->first();

            if ($vorhanden) {
                $token = $vorhanden->token;
                $expiresAt = \Illuminate\Support\Carbon::parse($vorhanden->expires_at);
            } else {
                // Token generieren (64 Zeichen, sicher), gültig 24 Stunden
                $token = bin2hex(random_bytes(32));
                $expiresAt = now()->addHours(24);

                DB::table('grading_student_tokens')->insert([
                    'token' => $token,
                    'session_id' => $session->id,
                    'schueler_id' => $schueler->id,
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'token' => $token,
                'url' => route('gradingDocumentation.publicStudentSession', ['token' => $token]),
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Fehler beim Generieren des QR-Tokens', [
                'session_id' => $session->id,
                'schueler_id' => $schueler->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Der QR-Code konnte nicht erzeugt werden. Bitte versuche es erneut.'
            ], 500);
        }
    }

    /**
     * Zeigt die öffentliche Schüler-Dokumentationsseite (ohne Anmeldung)
     */
    public function showPublicStudentSession($token)
    {
        // Token validieren
        $tokenData = DB::table('grading_student_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenData) {
            abort(404, 'Ungültiger oder abgelaufener Token');
        }

        // Session und Schüler laden
        $session = GradingDocumentationSession::with([
            'studentAnswers' => function($q) use ($tokenData) {
                $q->where('schueler_id', $tokenData->schueler_id);
            }
        ])->findOrFail($tokenData->session_id);

        $schueler = Schueler::findOrFail($tokenData->schueler_id);

        // Abgeschlossene Session bzw. abgeschlossener Schüler: Hinweis statt Fragen
        $abgeschlossen = $session->isCompleted() || $this->sessions()->isFinalized($session, (int) $schueler->id);

        // Im Modus "by_question" nur die bereits freigegebenen Fragen (wie in der Schüler-App)
        $questions = $abgeschlossen ? collect() : $this->sessions()->releasedQuestions($session);

        return view('paedDiary.documentation.public-student-session', [
            'token' => $token,
            'session' => $session,
            // Öffentliche Seite: nur der Vorname (Datensparsamkeit)
            'vorname' => $schueler->vorname,
            'questions' => $questions->map(fn ($q) => ['id' => $q->id, 'question' => $q->question])->values(),
            'answers' => $session->studentAnswers->pluck('self_rating', 'question_id'),
            'abgeschlossen' => $abgeschlossen,
            'wartetAufFreigabe' => !$abgeschlossen && $session->usesQuestionOrder() && $questions->isEmpty(),
            'nachFrage' => $session->usesQuestionOrder(),
        ]);
    }

    /**
     * Speichert eine Schülerantwort über den öffentlichen Token-Link
     */
    public function savePublicStudentAnswer(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'question_id' => 'required|exists:grading_questions,id',
            'self_rating' => 'required|integer|min:1|max:5',
        ]);

        // Token validieren
        $tokenData = DB::table('grading_student_tokens')
            ->where('token', $request->token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenData) {
            return response()->json(['message' => 'Ungültiger oder abgelaufener Token'], 403);
        }

        // Session muss offen sein, die Frage zum Graduierungssystem der Session gehören
        $session = GradingDocumentationSession::find($tokenData->session_id);
        if (!$session) {
            return response()->json(['message' => 'Ungültiger oder abgelaufener Token'], 403);
        }
        if ($fehler = $this->sessions()->schreibFehler($session, (int) $tokenData->schueler_id, (int) $request->question_id)) {
            return response()->json(['message' => $fehler[1]], $fehler[0]);
        }

        // Antwort speichern
        $answer = GradingStudentAnswer::updateOrCreate(
            [
                'session_id' => $tokenData->session_id,
                'schueler_id' => $tokenData->schueler_id,
                'question_id' => $request->question_id,
            ],
            [
                'self_rating' => $request->self_rating,
                'answered_at' => now(),
            ]
        );

        return response()->json(['answer' => $answer]);
    }

    /**
     * Öffnet eine abgeschlossene Session wieder
     */
    public function reopenSession(GradingDocumentationSession $session)
    {
        $this->authorize('update', $session);

        // Prüfen ob Session wiedergeöffnet werden kann
        if (!$session->canBeReopened()) {
            $maxDays = settings('session_reopen_days', 'config.grading_documentation');
            $daysSinceCompleted = $session->completed_at ? $session->completed_at->diffInDays(now()) : 0;

            return response()->json([
                'message' => "Die Session kann nicht wiedergeöffnet werden. Sie wurde vor {$daysSinceCompleted} Tagen abgeschlossen. Maximale Frist: {$maxDays} Tage."
            ], 422);
        }

        if ($this->sessions()->wiederoeffnen($session)) {
            return response()->json([
                'message' => 'Session wurde erfolgreich wiedergeöffnet.',
                'redirect' => $session->type === 'group'
                    ? route('gradingDocumentation.groupSession', $session->id)
                    : route('gradingDocumentation.individualSession', $session->id)
            ]);
        }

        return response()->json(['message' => 'Fehler beim Wiederöffnen der Session.'], 500);
    }

    /**
     * Aktualisiert die Beantwortungsreihenfolge einer offenen Session
     */
    public function updateAnswerOrderMode(Request $request, GradingDocumentationSession $session)
    {
        $this->authorize('update', $session);

        if ($session->isCompleted()) {
            return response()->json([
                'message' => 'Die Beantwortungsreihenfolge kann bei abgeschlossenen Sessions nicht geändert werden.'
            ], 422);
        }

        $request->validate([
            'answer_order_mode' => 'required|in:' . implode(',', GradingDocumentationSession::ANSWER_ORDER_MODES),
        ]);

        if (!app(GradingSessionService::class)->changeAnswerOrderMode($session, (string) $request->input('answer_order_mode'))) {
            return response()->json([
                'message' => 'Für diese Session ist die gewählte Beantwortungsreihenfolge nicht verfügbar.'
            ], 422);
        }

        return response()->json([
            'message' => 'Die Beantwortungsreihenfolge wurde aktualisiert.',
            'answer_order_mode' => $session->answer_order_mode,
            'answer_order_mode_label' => $session->answer_order_mode_label,
        ]);
    }

    /** Nutzt die angemeldete Person die neue Ansicht des Pädagogischen Tagebuchs? */
    private function neueAnsicht(): bool
    {
        return (bool) Auth::user()?->nutztNeueTagebuchAnsicht();
    }

    private function sessions(): GradingSessionService
    {
        return app(GradingSessionService::class);
    }
}
