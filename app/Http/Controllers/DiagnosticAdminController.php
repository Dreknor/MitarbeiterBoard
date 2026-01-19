<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiagnosticAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:manage diagnostics');
    }

    /**
     * Liste aller Bereiche
     */
    public function index()
    {
        $areas = DiagnosticArea::with(['stages.goals'])
            ->ordered()
            ->get();

        return view('diagnostics.admin.index', [
            'areas' => $areas,
        ]);
    }

    /**
     * Neuen Bereich anlegen
     */
    public function storeArea(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        try {
            // Höchste Sort-Order ermitteln
            $maxSortOrder = DiagnosticArea::max('sort_order') ?? 0;

            $area = DiagnosticArea::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'] ?? true,
                'sort_order' => $maxSortOrder + 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bereich erfolgreich erstellt',
                'area' => $area,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Erstellen des Bereichs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Erstellen',
            ], 500);
        }
    }

    /**
     * Bereich bearbeiten
     */
    public function updateArea(Request $request, DiagnosticArea $area)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        try {
            $area->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Bereich erfolgreich aktualisiert',
                'area' => $area,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Aktualisieren des Bereichs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Aktualisieren',
            ], 500);
        }
    }

    /**
     * Bereich löschen
     */
    public function destroyArea(DiagnosticArea $area)
    {
        try {
            $area->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bereich erfolgreich gelöscht',
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Löschen des Bereichs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Löschen',
            ], 500);
        }
    }

    /**
     * Sortierung der Bereiche ändern
     */
    public function reorderAreas(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|exists:diagnostic_areas,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                foreach ($validated['order'] as $index => $areaId) {
                    DiagnosticArea::where('id', $areaId)->update(['sort_order' => $index]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Sortierung erfolgreich gespeichert',
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Sortieren der Bereiche: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Sortieren',
            ], 500);
        }
    }

    /**
     * Stufe anlegen
     */
    public function storeStage(Request $request, DiagnosticArea $area)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'goal_description' => 'nullable|string',
        ]);

        try {
            // Höchste Sort-Order in diesem Bereich ermitteln
            $maxSortOrder = $area->stages()->max('sort_order') ?? 0;

            $stage = DiagnosticStage::create([
                'diagnostic_area_id' => $area->id,
                'name' => $validated['name'],
                'code' => $validated['code'],
                'goal_description' => $validated['goal_description'] ?? null,
                'sort_order' => $maxSortOrder + 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stufe erfolgreich erstellt',
                'stage' => $stage,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Erstellen der Stufe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Erstellen',
            ], 500);
        }
    }

    /**
     * Stufe bearbeiten
     */
    public function updateStage(Request $request, DiagnosticStage $stage)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'goal_description' => 'nullable|string',
        ]);

        try {
            $stage->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Stufe erfolgreich aktualisiert',
                'stage' => $stage,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Aktualisieren der Stufe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Aktualisieren',
            ], 500);
        }
    }

    /**
     * Stufe löschen
     */
    public function destroyStage(DiagnosticStage $stage)
    {
        try {
            $stage->delete();

            return response()->json([
                'success' => true,
                'message' => 'Stufe erfolgreich gelöscht',
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Löschen der Stufe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Löschen',
            ], 500);
        }
    }

    /**
     * Sortierung der Stufen ändern
     */
    public function reorderStages(Request $request, DiagnosticArea $area)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|exists:diagnostic_stages,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                foreach ($validated['order'] as $index => $stageId) {
                    DiagnosticStage::where('id', $stageId)->update(['sort_order' => $index]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Sortierung erfolgreich gespeichert',
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Sortieren der Stufen: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Sortieren',
            ], 500);
        }
    }

    /**
     * Ziel anlegen
     */
    public function storeGoal(Request $request, DiagnosticStage $stage)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'description' => 'required|string',
        ]);

        try {
            // Höchste Sort-Order in dieser Stufe ermitteln
            $maxSortOrder = $stage->goals()->max('sort_order') ?? 0;

            $goal = DiagnosticGoal::create([
                'diagnostic_stage_id' => $stage->id,
                'code' => $validated['code'],
                'description' => $validated['description'],
                'sort_order' => $maxSortOrder + 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ziel erfolgreich erstellt',
                'goal' => $goal,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Erstellen des Ziels: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Erstellen',
            ], 500);
        }
    }

    /**
     * Ziel bearbeiten
     */
    public function updateGoal(Request $request, DiagnosticGoal $goal)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'description' => 'required|string',
        ]);

        try {
            $goal->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Ziel erfolgreich aktualisiert',
                'goal' => $goal,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Aktualisieren des Ziels: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Aktualisieren',
            ], 500);
        }
    }

    /**
     * Ziel löschen
     */
    public function destroyGoal(DiagnosticGoal $goal)
    {
        try {
            $goal->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ziel erfolgreich gelöscht',
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Löschen des Ziels: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Löschen',
            ], 500);
        }
    }

    /**
     * Sortierung der Ziele ändern
     */
    public function reorderGoals(Request $request, DiagnosticStage $stage)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|exists:diagnostic_goals,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                foreach ($validated['order'] as $index => $goalId) {
                    DiagnosticGoal::where('id', $goalId)->update(['sort_order' => $index]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Sortierung erfolgreich gespeichert',
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Sortieren der Ziele: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Sortieren',
            ], 500);
        }
    }
}

