<?php

namespace App\Http\Controllers;

use App\Services\Updater\UpdaterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UpdateController extends Controller
{
    public function __construct(private UpdaterService $updater)
    {
        $this->middleware('permission:make updates');
    }

    public function index()
    {
        $status = $this->updater->status();
        $active = $this->updater->isActive();

        return view('updater.index', [
            'status' => $status,
            'active' => $active,
            'log' => $this->updater->logTail(),
            'version' => $this->updater->currentVersion(),
            'remoteRef' => $this->updater->branch() ? $this->updater->remoteRef() : null,
            'commits' => $this->updater->branch() ? $this->updater->pendingCommits() : [],
            'checks' => $active ? [] : $this->updater->preflight(),
        ]);
    }

    /**
     * Holt den aktuellen Stand vom Git-Server (git fetch).
     */
    public function check()
    {
        try {
            $this->updater->fetch();
        } catch (Throwable $e) {
            return redirectBack('danger', 'Suche nach Updates fehlgeschlagen: ' . $e->getMessage());
        }

        $count = count($this->updater->pendingCommits());

        return redirectBack(
            $count ? 'info' : 'success',
            $count ? "{$count} neue Änderung(en) verfügbar." : 'Die Anwendung ist aktuell.'
        );
    }

    public function update(Request $request)
    {
        if (! $this->updater->preflightPassed()) {
            return redirectBack('danger', 'Die Vorabprüfung ist fehlgeschlagen – das Update wurde nicht gestartet.');
        }

        try {
            $this->updater->start($request->user());
        } catch (Throwable $e) {
            return redirectBack('danger', $e->getMessage());
        }

        return redirect()->route('updater.index')
            ->with(['type' => 'info', 'Meldung' => 'Das Update wurde gestartet.']);
    }

    /**
     * Status und Log für die Live-Anzeige. Auch im Wartungsmodus erreichbar
     * (siehe PreventRequestsDuringMaintenance).
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => $this->updater->status(),
            'log' => $this->updater->logTail(),
        ]);
    }
}
