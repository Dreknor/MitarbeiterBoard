<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Services\OxCalendarService;
use Illuminate\Http\Request;

/**
 * Admin-Controller für die Kalender-Verwaltung.
 *
 * Routen (Prefix: /calendar/admin, Middleware: permission:manage calendar):
 * - GET  /           → index()          Admin-Übersicht mit Verbindungsstatus
 * - POST /kalender   → storeKalender()  Neuen Kalender anlegen
 * - PUT  /kalender/{id} → updateKalender() Kalender aktualisieren
 * - DEL  /kalender/{id} → destroyKalender() SoftDelete
 * - POST /kalender/{id}/gruppen → updateGruppen() Pivot-Sync
 * - POST /sync       → triggerSync()    Manueller Sync
 * - GET  /logs       → logs()           Sync-Log-Ansicht (paginiert, filterbar)
 *
 * @see \App\Models\OxCalendar
 * @see \App\Services\OxCalendarService
 */
class CalendarAdminController extends Controller
{
    /**
     * Admin-Übersicht: Alle Kalender mit Gruppen-Zuordnungen.
     */
    public function index()
    {
        $kalender = OxCalendar::withTrashed()
            ->with('groups')
            ->withCount('termine')
            ->orderBy('name')
            ->get();

        $gruppen = Group::orderBy('name')->get();

        $connectionStatus = app(OxCalendarService::class)->isEnabled()
            ? app(OxCalendarService::class)->testConnection()
            : ['success' => false, 'message' => 'Modul deaktiviert'];

        return view('calendar.admin.index', [
            'kalender'         => $kalender,
            'gruppen'          => $gruppen,
            'connectionStatus' => $connectionStatus,
        ]);
    }

    /**
     * Neuen Kalender hinzufügen.
     */
    public function storeKalender(Request $request)
    {
        $validated = $request->validate([
            'ox_calendar_id' => ['required', 'string', 'max:500'],
            'name'           => ['required', 'string', 'max:255'],
            'farbe'          => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'beschreibung'   => ['nullable', 'string', 'max:1000'],
            'sichtbar'       => ['boolean'],
            'schreibbar'     => ['boolean'],
        ]);

        $calendar = OxCalendar::create($validated);

        return redirectBack('success', "Kalender \"{$calendar->name}\" wurde angelegt.");
    }

    /**
     * Kalender bearbeiten.
     */
    public function updateKalender(Request $request, OxCalendar $kalender)
    {
        $validated = $request->validate([
            'ox_calendar_id' => ['required', 'string', 'max:500'],
            'name'           => ['required', 'string', 'max:255'],
            'farbe'          => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'beschreibung'   => ['nullable', 'string', 'max:1000'],
            'sichtbar'       => ['boolean'],
            'schreibbar'     => ['boolean'],
        ]);

        $kalender->update($validated);

        return redirectBack('success', "Kalender \"{$kalender->name}\" wurde aktualisiert.");
    }

    /**
     * Kalender löschen (SoftDelete).
     */
    public function destroyKalender(OxCalendar $kalender)
    {
        $name = $kalender->name;
        $kalender->delete();

        return redirectBack('success', "Kalender \"{$name}\" wurde gelöscht.");
    }

    /**
     * Gruppen-Zuordnung aktualisieren (Pivot-Sync).
     */
    public function updateGruppen(Request $request, OxCalendar $kalender)
    {
        $validated = $request->validate([
            'gruppen'                  => ['nullable', 'array'],
            'gruppen.*.group_id'       => ['required', 'exists:groups,id'],
            'gruppen.*.schreibbar'     => ['boolean'],
        ]);

        // Pivot-Daten aufbauen: [group_id => ['schreibbar' => bool]]
        $syncData = [];
        foreach ($validated['gruppen'] ?? [] as $item) {
            $syncData[$item['group_id']] = [
                'schreibbar' => $item['schreibbar'] ?? false,
            ];
        }

        $kalender->groups()->sync($syncData);

        return redirectBack('success', "Gruppen-Zuordnungen für \"{$kalender->name}\" aktualisiert.");
    }

    /**
     * Manuellen Sync auslösen.
     */
    public function triggerSync(OxCalendarService $service)
    {
        if (!$service->isEnabled()) {
            return redirectBack('warning', 'Kalender-Modul ist nicht aktiviert.');
        }

        $results = $service->syncAll();

        $total  = collect($results)->sum(fn ($r) => ($r['created'] ?? 0) + ($r['updated'] ?? 0) + ($r['deleted'] ?? 0));
        $errors = collect($results)->sum(fn ($r) => $r['errors'] ?? 0);

        $message = "Sync abgeschlossen: {$total} Änderungen";
        if ($errors > 0) {
            $message .= ", {$errors} Fehler (Details in Sync-Logs)";
        }

        return redirectBack($errors > 0 ? 'warning' : 'success', $message);
    }

    /**
     * Sync-Logs anzeigen.
     */
    public function logs(Request $request)
    {
        $query = OxSyncLog::with(['kalender', 'benutzer'])
            ->orderByDesc('created_at');

        // Filter
        if ($request->filled('kalender')) {
            $query->where('ox_calendar_id', $request->kalender);
        }
        if ($request->filled('aktion')) {
            $query->where('aktion', $request->aktion);
        }

        $logs = $query->paginate(50);

        $kalender = OxCalendar::orderBy('name')->get();
        $aktionen = ['sync_start', 'sync_complete', 'create', 'update', 'delete', 'error'];

        return view('calendar.admin.logs', [
            'logs'             => $logs,
            'kalender'         => $kalender,
            'aktionen'         => $aktionen,
            'selectedKalender' => $request->kalender,
            'selectedAktion'   => $request->aktion,
        ]);
    }
}

