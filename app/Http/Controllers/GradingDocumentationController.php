<?php

namespace App\Http\Controllers;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingStudentAnswer;
use App\Models\GradingTeacherAssessment;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\PaedDiaryClassGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        return view('paedDiary.documentation.index', [
            'klassen' => $klassen,
            'groups' => $groups,
            'openSessions' => $openSessions,
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
        \Log::info('Group Session Data:', [
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
            'teacherAssessments'
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

        $sessions = GradingDocumentationSession::where('klasse_id', $klasse->id)
            ->where(function($q) use ($schueler) {
                $q->where('type', 'group')
                  ->orWhere(function($q2) use ($schueler) {
                      $q2->where('type', 'individual')
                         ->where('schueler_id', $schueler->id);
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
}

