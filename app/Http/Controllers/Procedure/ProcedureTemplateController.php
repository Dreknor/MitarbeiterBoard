<?php

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Services\Procedure\ProcedureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller für Template-spezifische Aktionen (Phase 3).
 * Phase-1-Hauptlogik verbleibt in ProcedureController – hier nur Ergänzungen.
 */
class ProcedureTemplateController extends Controller
{
    public function __construct(private readonly ProcedureService $procedureService) {}

    /**
     * POST /procedure/templates/{procedure}/clone
     * Dupliziert eine Vorlage inkl. aller Schritte (§B-05).
     */
    public function clone(Request $request, Procedure $procedure): RedirectResponse
    {
        if (!$request->user()->can('manage procedures')) {
            abort(403);
        }

        if ($procedure->started_at !== null) {
            return back()->with([
                'Meldung' => 'Nur Vorlagen können dupliziert werden.',
                'type'    => 'danger',
            ]);
        }

        try {
            $result = $this->procedureService->cloneTemplate($procedure, $request->user()->id);

            return redirect()
                ->route('procedure.index', ['#templates'])
                ->with([
                    'Meldung' => 'Vorlage „' . $result['legacy']->name . '" wurde angelegt.',
                    'type'    => 'success',
                ]);
        } catch (\Throwable $e) {
            report($e);
            return back()->with([
                'Meldung' => 'Fehler beim Duplizieren: ' . $e->getMessage(),
                'type'    => 'danger',
            ]);
        }
    }
}

