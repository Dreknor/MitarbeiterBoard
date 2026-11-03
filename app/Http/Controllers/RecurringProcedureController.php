<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringProcedureRequest;
use App\Models\RecurringProcedure;
use App\Services\Procedure\RecurringProcedureRunner;
use Illuminate\Support\Facades\Log;

class RecurringProcedureController extends Controller
{
    public function __construct(private readonly RecurringProcedureRunner $runner)
    {
        $this->middleware('permission:manage procedures');
    }

    /**
     * Store a newly created resource in storage.
     * Pflichtfelder je Auslösertyp prüft StoreRecurringProcedureRequest (required_if).
     */
    public function store(StoreRecurringProcedureRequest $request)
    {
        $recurringProcedure = RecurringProcedure::create($request->validated());
        $recurringProcedure->update([
            'next_trigger_at' => $this->runner->calculateNextTrigger($recurringProcedure),
        ]);

        return redirect()->back()->with([
            'Meldung' => 'Wiederkehrender Prozess wurde erfolgreich erstellt.',
            'type' => 'success'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecurringProcedure $recurringProcedure)
    {
        if (auth()->user()->cannot('delete procedures')) {
            return redirect()->back()->with([
                'Meldung' => 'Sie haben keine Berechtigung, diesen wiederkehrenden Prozess zu löschen.',
                'type' => 'danger'
            ]);
        }
        $recurringProcedure->delete();

        return redirect()->back()->with([
            'Meldung' => 'Wiederkehrender Prozess wurde erfolgreich gelöscht.',
            'type' => 'success'
        ]);
    }

    /**
     * Phase 1 / B-30: Pausieren / Aktivieren.
     */
    public function toggle(RecurringProcedure $recurringProcedure)
    {
        $recurringProcedure->update([
            'active' => !($recurringProcedure->active ?? true),
        ]);

        if (request()->wantsJson()) {
            return response()->json(['data' => ['id' => $recurringProcedure->id, 'active' => $recurringProcedure->active]]);
        }

        return redirect()->back()->with([
            'Meldung' => $recurringProcedure->active ? 'Aktiviert' : 'Pausiert',
            'type'    => 'success',
        ]);
    }

    /**
     * Startet einen wiederkehrenden Prozess manuell (POST /procedure/recurring/{id}/trigger).
     */
    public function start(RecurringProcedure $recurringProcedure)
    {
        try {
            $startedProcedure = $this->runner->trigger($recurringProcedure);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with([
                'Meldung' => 'Prozess konnte nicht gestartet werden: ' . $e->getMessage(),
                'type'    => 'danger',
            ]);
        }

        return redirect(url('procedure/'.$startedProcedure->id.'/start'))->with([
            'Meldung' => 'Wiederkehrender Prozess wurde erfolgreich gestartet.',
            'type' => 'success'
        ]);
    }

    /**
     * Scheduler (täglich): fällige wiederkehrende Prozesse starten.
     */
    public function checkStart(): void
    {
        try {
            $this->runner->check();
        } catch (\Throwable $e) {
            Log::error('RecurringProcedureRunner Fehler', ['error' => $e->getMessage()]);
        }
    }
}
