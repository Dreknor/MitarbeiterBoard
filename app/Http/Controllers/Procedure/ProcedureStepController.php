<?php

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Controller;
use App\Models\Procedure_Step;
use App\Services\Procedure\ProcedureStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AJAX-Controller für Schritt-Aktionen (Phase 3):
 *  - complete  (B-15)
 *  - reopen    (B-16)
 *  - move      (B-09, Drag-&-Drop Reihenfolge)
 */
class ProcedureStepController extends Controller
{
    public function __construct(private readonly ProcedureStepService $stepService) {}

    /**
     * POST /procedure/steps/{step}/complete
     * Markiert einen Schritt als erledigt.
     */
    public function complete(Request $request, Procedure_Step $step): JsonResponse
    {
        $user = $request->user();

        // Berechtigung: eigene Schritte oder manage
        $canComplete = $user->can('complete own procedure steps')
            && $step->users->contains('id', $user->id);
        $canManage   = $user->can('manage procedures');

        if (!$canComplete && !$canManage) {
            return response()->json(['message' => 'Keine Berechtigung.'], 403);
        }

        if ($step->done) {
            return response()->json(['message' => 'Schritt bereits erledigt.'], 422);
        }

        $procedureCompleted = $this->stepService->complete($step, $user);

        return response()->json([
            'message'             => 'Schritt als erledigt markiert.',
            'completed_at'        => $step->fresh()->completed_at?->format('d.m.Y H:i'),
            'procedure_completed' => $procedureCompleted,
        ]);
    }

    /**
     * POST /procedure/steps/{step}/reopen
     * Öffnet einen erledigten Schritt wieder (B-16).
     */
    public function reopen(Request $request, Procedure_Step $step): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('manage procedures')) {
            return response()->json(['message' => 'Keine Berechtigung.'], 403);
        }

        if (!$step->done) {
            return response()->json(['message' => 'Schritt ist nicht erledigt.'], 422);
        }

        $this->stepService->reopen($step);

        return response()->json(['message' => 'Schritt wieder geöffnet.']);
    }

    /**
     * PATCH /procedure/steps/{step}/move
     * Schritt verschieben / Reihenfolge ändern (B-09, Drag-&-Drop).
     *
     * Body: { parent_id: int|null, sort_order: int }
     */
    public function move(Request $request, Procedure_Step $step): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('manage procedures')) {
            return response()->json(['message' => 'Keine Berechtigung.'], 403);
        }

        $request->validate([
            'parent_id'  => 'nullable|integer|exists:procedure_steps,id',
            'sort_order' => 'required|integer|min:0',
        ]);

        // Wenn parent_id angegeben: sicherstellen, dass er im selben Prozess ist
        if ($request->parent_id) {
            $parent = Procedure_Step::find($request->parent_id);
            if (!$parent || $parent->procedure_id !== $step->procedure_id) {
                return response()->json(['message' => 'Elternschritt gehört nicht zum selben Prozess.'], 422);
            }
        }

        try {
            $this->stepService->moveStep($step, $request->parent_id, (int) $request->sort_order);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Schritt verschoben.']);
    }

    /**
     * POST /procedure/steps/reorder
     * Bulk-Neusortierung der Kinder eines Elternknotens nach Drag-&-Drop.
     *
     * Body: { procedure_id: int, parent_id: int|null, ordered_ids: int[] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('manage procedures')) {
            return response()->json(['message' => 'Keine Berechtigung.'], 403);
        }

        $validated = $request->validate([
            'procedure_id' => 'required|integer|exists:procedures,id',
            'parent_id'    => 'nullable|integer|exists:procedure_steps,id',
            'ordered_ids'  => 'required|array',
            'ordered_ids.*' => 'integer|exists:procedure_steps,id',
        ]);

        $this->stepService->reorderSiblings(
            $validated['procedure_id'],
            $validated['parent_id'] ?? null,
            $validated['ordered_ids']
        );

        return response()->json(['message' => 'Reihenfolge gespeichert.']);
    }
}

