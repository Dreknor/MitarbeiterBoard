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

        return view('paedDiary.documentation.index', [
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
        ]);

        $user = Auth::user();
        $klasse = $user->paed_klassen()->findOrFail($request->klasse_id);

        if (!$klasse->gradingSystem) {
            return response()->json(['message' => 'Dieser Klasse ist kein Graduierungssystem zugeordnet.'], 422);
        }

        // Prüfen ob bereits eine offene Session für diese Klasse/Gruppe existiert
        $existingSession = GradingDocumentationSession::where('user_id', $user->id)
            ->where('klasse_id', $klasse->id)
            ->where('type', 'group')
            ->where('group_id', $request->group_id)
            ->whereNull('completed_at')
            ->first();

        if ($existingSession) {
            // Bestehende Session fortsetzen
            return response()->json([
                'session' => $existingSession,
                'redirect' => route('gradingDocumentation.groupSession', $existingSession->id),
                'resumed' => true
            ]);
        }

        // Neue Session erstellen
        $session = GradingDocumentationSession::create([
            'klasse_id' => $klasse->id,
            'grading_system_id' => $klasse->grading_system_id,
            'user_id' => $user->id,
            'type' => 'group',
            'group_id' => $request->group_id,
            'started_at' => now(),
        ]);

        return response()->json([
            'session' => $session,
            'redirect' => route('gradingDocumentation.groupSession', $session->id),
            'resumed' => false
        ]);
    }

    /**
     * Zeigt die Gruppen-Dokumentationssession
     */
    public function showGroupSession(GradingDocumentationSession $session)
    {
        $this->authorize('view', $session);

        $session->load([
            'klasse.schueler' => function($q) {
                $q->orderBy('nachname')->orderBy('vorname');
            },
            'gradingSystem.questions' => function($q) {
                $q->where('active', true)->orderBy('sort_order');
            },
            'studentAnswers'
        ]);

        // Schüler filtern nach Gruppe falls vorhanden
        $schueler = $session->klasse->schueler;
        if ($session->group_id) {
            $group = $session->group;
            // Filter Schüler die zur Gruppe gehören falls implementiert
        }

        // Debug: Log the data being passed to the view
        Log::info('Group Session Data:', [
            'session_id' => $session->id,
            'klasse_id' => $session->klasse_id,
            'klasse_name' => $session->klasse->name,
            'schueler_count' => $schueler->count(),
            'schueler_ids' => $schueler->pluck('id')->toArray(),
            'questions_count' => $session->gradingSystem->questions->count(),
            'questions_ids' => $session->gradingSystem->questions->pluck('id')->toArray(),
            'answers_count' => $session->studentAnswers->count(),
        ]);

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

        // Bei individueller Session nur den betroffenen Schüler laden
        if ($session->type === 'individual' && $session->schueler_id) {
            $session->load('schueler');
            $schueler = collect([$session->schueler]);
        } else {
            // Bei Gruppensession alle Schüler der Klasse laden
            $session->load([
                'klasse.schueler' => function($q) {
                    $q->orderBy('nachname')->orderBy('vorname');
                }
            ]);
            $schueler = $session->klasse->schueler;
        }

        return view('paedDiary.documentation.teacher-assessment', [
            'session' => $session,
            'schueler' => $schueler,
            'questions' => $session->gradingSystem->questions,
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

        $session->update(['completed_at' => now()]);

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

        if (!$klasse->gradingSystem) {
            return response()->json(['message' => 'Dieser Klasse ist kein Graduierungssystem zugeordnet.'], 422);
        }

        // Prüfen ob bereits eine offene Session für diesen Schüler existiert
        $existingSession = GradingDocumentationSession::where('user_id', $user->id)
            ->where('klasse_id', $klasse->id)
            ->where('type', 'individual')
            ->where('schueler_id', $request->schueler_id)
            ->whereNull('completed_at')
            ->first();

        if ($existingSession) {
            // Bestehende Session fortsetzen
            return response()->json([
                'session' => $existingSession,
                'redirect' => route('gradingDocumentation.individualSession', $existingSession->id),
                'resumed' => true
            ]);
        }

        // Neue Session erstellen
        $session = GradingDocumentationSession::create([
            'klasse_id' => $klasse->id,
            'grading_system_id' => $klasse->grading_system_id,
            'user_id' => $user->id,
            'type' => 'individual',
            'schueler_id' => $request->schueler_id,
            'started_at' => now(),
        ]);

        return response()->json([
            'session' => $session,
            'redirect' => route('gradingDocumentation.individualSession', $session->id),
            'resumed' => false
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

        return response()->json(['session' => $session]);
    }

    /**
     * Generiert einen temporären Token für QR-Code Zugriff auf Schüler-Dokumentation
     */
    public function generateStudentQRToken(GradingDocumentationSession $session, Schueler $schueler)
    {
        try {
            $this->authorize('update', $session);

            // Prüfen ob Schüler zur Session gehört
            if ($session->type === 'individual' && $session->schueler_id !== $schueler->id) {
                return response()->json([
                    'message' => 'Schüler gehört nicht zu dieser Session'
                ], 403);
            }

            if ($session->type === 'group' && $session->klasse_id !== $schueler->klasse_id) {
                return response()->json([
                    'message' => 'Schüler gehört nicht zu dieser Klasse'
                ], 403);
            }

            // Token generieren (64 Zeichen, sicher)
            $token = bin2hex(random_bytes(32));

            // Token in der Datenbank speichern (mit Ablaufzeit 24 Stunden)
            DB::table('grading_student_tokens')->insert([
                'token' => $token,
                'session_id' => $session->id,
                'schueler_id' => $schueler->id,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $url = route('gradingDocumentation.publicStudentSession', ['token' => $token]);

            return response()->json([
                'token' => $token,
                'url' => $url,
                'expires_at' => now()->addHours(24)->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Generieren des QR-Tokens', [
                'session_id' => $session->id ?? null,
                'schueler_id' => $schueler->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Fehler beim Generieren des Tokens: ' . $e->getMessage()
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
            'gradingSystem.questions' => function($q) {
                $q->where('active', true)->orderBy('sort_order');
            },
            'studentAnswers' => function($q) use ($tokenData) {
                $q->where('schueler_id', $tokenData->schueler_id);
            }
        ])->findOrFail($tokenData->session_id);

        $schueler = Schueler::findOrFail($tokenData->schueler_id);

        return view('paedDiary.documentation.public-student-session', [
            'token' => $token,
            'session' => $session,
            'schueler' => $schueler,
            'questions' => $session->gradingSystem->questions,
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

        if ($session->reopen()) {
            return response()->json([
                'message' => 'Session wurde erfolgreich wiedergeöffnet.',
                'redirect' => $session->type === 'group'
                    ? route('gradingDocumentation.groupSession', $session->id)
                    : route('gradingDocumentation.individualSession', $session->id)
            ]);
        }

        return response()->json(['message' => 'Fehler beim Wiederöffnen der Session.'], 500);
    }
}


