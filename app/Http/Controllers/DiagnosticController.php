<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticGoalComment;
use App\Models\DiagnosticSession;
use App\Models\DiagnosticStage;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Services\DiagnosticService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiagnosticController extends Controller
{
    protected $diagnosticService;

    public function __construct(DiagnosticService $diagnosticService)
    {
        $this->middleware('auth');
        $this->middleware('permission:view diagnostics');
        $this->diagnosticService = $diagnosticService;
    }

    /**
     * Übersicht - Klassenwahl
     */
    public function index()
    {
        $user = Auth::user();
        $klassen = $user->paed_klassen()->withCount('schueler')->orderBy('name')->get();

        if ($klassen->isEmpty()) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Klassen für Diagnosebögen zugewiesen.'
            ]);
        }

        return view('diagnostics.index', [
            'klassen' => $klassen,
        ]);
    }

    /**
     * Schülerliste der Klasse
     */
    public function selectStudent(Klasse $klasse)
    {
        $user = Auth::user();

        // Prüfen ob User Zugriff auf diese Klasse hat
        if (!$user->paed_klassen()->where('klassen.id', $klasse->id)->exists()) {
            abort(403, 'Kein Zugriff auf diese Klasse.');
        }

        $schueler = $klasse->schueler()->orderBy('nachname')->orderBy('vorname')->get();

        return view('diagnostics.students', [
            'klasse' => $klasse,
            'schueler' => $schueler,
        ]);
    }

    /**
     * Bereichswahl für Schüler
     */
    public function selectArea(Schueler $schueler)
    {
        $user = Auth::user();

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        if (!$user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists()) {
            abort(403, 'Kein Zugriff auf diesen Schüler.');
        }

        $areas = DiagnosticArea::active()->ordered()->get();

        // Status für jeden Bereich ermitteln
        $areaStatus = [];
        foreach ($areas as $area) {
            $openSession = DiagnosticSession::where('schueler_id', $schueler->id)
                ->where('diagnostic_area_id', $area->id)
                ->where('is_completed', false)
                ->first();

            $completedCount = DiagnosticSession::where('schueler_id', $schueler->id)
                ->where('diagnostic_area_id', $area->id)
                ->where('is_completed', true)
                ->count();

            $areaStatus[$area->id] = [
                'has_open_session' => (bool) $openSession,
                'open_session' => $openSession,
                'completed_count' => $completedCount,
                'can_start' => $this->diagnosticService->canStartNewSession($schueler, $area),
            ];
        }

        return view('diagnostics.areas', [
            'schueler' => $schueler,
            'areas' => $areas,
            'areaStatus' => $areaStatus,
        ]);
    }

    /**
     * Session starten oder fortsetzen
     */
    public function start(Schueler $schueler, DiagnosticArea $area)
    {
        $this->authorize('create', [DiagnosticSession::class, $schueler]);

        try {
            $session = $this->diagnosticService->getOrCreateSession($schueler, $area, Auth::user());

            return redirect()->route('diagnostic.session', $session->id);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'type' => 'error',
                'Meldung' => 'Fehler beim Starten der Session: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Erfassungs-Formular anzeigen
     */
    public function showSession(DiagnosticSession $session)
    {
        $this->authorize('view', $session);

        // Lade Session mit allen Beziehungen
        $session->load([
            'schueler.klasse',
            'area.stages.goals',
            'assessments.goal',
            'stageNotes',
        ]);

        // Lade historische Daten für alle Ziele
        $historicalData = [];
        foreach ($session->area->stages as $stage) {
            foreach ($stage->goals as $goal) {
                $historicalData[$goal->id] = $this->diagnosticService->getHistoricalData(
                    $goal,
                    $session->schueler,
                    3
                );
            }
        }

        // Lade Kommentare für den Schüler
        $comments = DiagnosticGoalComment::where('schueler_id', $session->schueler_id)
            ->with('goal', 'user')
            ->get()
            ->groupBy('diagnostic_goal_id');

        // Formatiere Kommentare für JavaScript
        $formattedComments = $comments->mapWithKeys(function($commentGroup, $goalId) {
            return [$goalId => $commentGroup->map(function($comment) {
                return [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'user_name' => $comment->user->name,
                    'created_at' => $comment->created_at->format('d.m.Y H:i'),
                ];
            })->values()];
        });

        return view('diagnostics.session', [
            'session' => $session,
            'historicalData' => $historicalData,
            'comments' => $comments,
            'formattedComments' => $formattedComments,
        ]);
    }

    /**
     * AJAX - Einzelnes Ziel speichern
     */
    public function saveAssessment(Request $request, DiagnosticSession $session)
    {
        $this->authorize('update', $session);

        $validated = $request->validate([
            'goal_id' => 'required|exists:diagnostic_goals,id',
            'rating' => 'nullable|in:white,gray,dark_gray',
        ]);

        try {
            $goal = DiagnosticGoal::findOrFail($validated['goal_id']);
            $assessment = $this->diagnosticService->saveAssessment(
                $session,
                $goal,
                $validated['rating']
            );

            return response()->json([
                'success' => true,
                'message' => 'Bewertung gespeichert',
                'assessment' => $assessment,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Speichern der Bewertung: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Speichern',
            ], 500);
        }
    }

    /**
     * AJAX - Stufen-Notiz speichern
     */
    public function saveStageNote(Request $request, DiagnosticSession $session, DiagnosticStage $stage)
    {
        $this->authorize('update', $session);

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        try {
            $stageNote = $this->diagnosticService->saveStageNote(
                $session,
                $stage,
                $validated['notes']
            );

            return response()->json([
                'success' => true,
                'message' => 'Notiz gespeichert',
                'stageNote' => $stageNote,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Speichern der Stufen-Notiz: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Speichern',
            ], 500);
        }
    }

    /**
     * Session abschließen
     */
    public function complete(DiagnosticSession $session)
    {
        $this->authorize('complete', $session);

        try {
            $this->diagnosticService->completeSession($session);

            return redirect()->route('diagnostic.areas', $session->schueler_id)->with([
                'type' => 'success',
                'Meldung' => 'Session erfolgreich abgeschlossen.',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'type' => 'error',
                'Meldung' => 'Fehler beim Abschließen der Session: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Session wieder öffnen (nur Admins)
     */
    public function reopen(DiagnosticSession $session)
    {
        $this->authorize('reopen', $session);

        try {
            $this->diagnosticService->reopenSession($session);

            return redirect()->route('diagnostic.session', $session->id)->with([
                'type' => 'success',
                'Meldung' => 'Session wurde wieder geöffnet.',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'type' => 'error',
                'Meldung' => 'Fehler beim Öffnen der Session: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Historische Daten anzeigen
     */
    public function history(Schueler $schueler, DiagnosticArea $area)
    {
        $user = Auth::user();

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        if (!$user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists()) {
            abort(403, 'Kein Zugriff auf diesen Schüler.');
        }

        $sessions = $this->diagnosticService->getSessionsForSchuelerAndArea($schueler, $area, true);

        return view('diagnostics.history', [
            'schueler' => $schueler,
            'area' => $area,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Übersicht aktuelle Ziele
     */
    public function currentGoals(Schueler $schueler)
    {
        $user = Auth::user();

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        if (!$user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists()) {
            abort(403, 'Kein Zugriff auf diesen Schüler.');
        }

        $currentGoals = DiagnosticAssessment::where('is_current_goal', true)
            ->whereHas('session', function ($query) use ($schueler) {
                $query->where('schueler_id', $schueler->id);
            })
            ->with([
                'goal.stage.area',
                'session'
            ])
            ->get();

        return view('diagnostics.current-goals', [
            'schueler' => $schueler,
            'currentGoals' => $currentGoals,
        ]);
    }

    /**
     * Ziel als "aktuell" markieren/demarkieren
     */
    public function toggleCurrentGoal(Request $request, DiagnosticAssessment $assessment)
    {
        $this->authorize('update', $assessment->session);

        try {
            $assessment->update([
                'is_current_goal' => !$assessment->is_current_goal,
            ]);

            return response()->json([
                'success' => true,
                'is_current_goal' => $assessment->is_current_goal,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Aktualisieren',
            ], 500);
        }
    }

    /**
     * Kommentar zu Ziel hinzufügen
     */
    public function storeGoalComment(Request $request, DiagnosticGoal $goal, Schueler $schueler)
    {
        $user = Auth::user();

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        if (!$user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists()) {
            abort(403, 'Kein Zugriff auf diesen Schüler.');
        }

        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        try {
            $comment = DiagnosticGoalComment::create([
                'diagnostic_goal_id' => $goal->id,
                'schueler_id' => $schueler->id,
                'user_id' => $user->id,
                'comment' => $validated['comment'],
            ]);

            $comment->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Kommentar gespeichert',
                'comment' => $comment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Speichern',
            ], 500);
        }
    }

    /**
     * Kommentar bearbeiten
     */
    public function updateGoalComment(Request $request, DiagnosticGoalComment $comment)
    {
        // Nur Autor oder Admin darf bearbeiten
        if ($comment->user_id !== Auth::id() && !Auth::user()->hasPermissionTo('manage diagnostics')) {
            abort(403, 'Kein Zugriff auf diesen Kommentar.');
        }

        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        try {
            $comment->update(['comment' => $validated['comment']]);

            return response()->json([
                'success' => true,
                'message' => 'Kommentar aktualisiert',
                'comment' => $comment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Aktualisieren',
            ], 500);
        }
    }

    /**
     * Kommentar löschen
     */
    public function deleteGoalComment(DiagnosticGoalComment $comment)
    {
        // Nur Autor oder Admin darf löschen
        if ($comment->user_id !== Auth::id() && !Auth::user()->hasPermissionTo('manage diagnostics')) {
            abort(403, 'Kein Zugriff auf diesen Kommentar.');
        }

        try {
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kommentar gelöscht',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Löschen',
            ], 500);
        }
    }
}

