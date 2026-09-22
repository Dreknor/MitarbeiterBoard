<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Illuminate\Http\JsonResponse;
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

    /**
     * Health-Check-Endpoint für externes Monitoring (TODO 26).
     *
     * Liefert JSON mit Kalender-Modulstatus.
     * HTTP 200 = gesund, HTTP 503 = kritisches Problem.
     *
     * @see routes/web.php: GET /calendar/admin/health.json
     */
    public function health(): JsonResponse
    {
        $service = app(OxCalendarService::class);
        $checks  = [];
        $healthy = true;

        // 1. Modul aktiviert?
        $checks['modul_aktiviert'] = $service->isEnabled();
        if (!$checks['modul_aktiviert']) {
            $healthy = false;
        }

        // 2. OX-Verbindung testen
        if ($service->isEnabled()) {
            $connection = $service->testConnection();
            $checks['ox_erreichbar'] = $connection['success'];
            $checks['ox_status']     = $connection['status'] ?? null;
            $checks['ox_message']    = $connection['message'];
            if (!$connection['success']) {
                $healthy = false;
            }
        } else {
            $checks['ox_erreichbar'] = false;
            $checks['ox_status']     = null;
            $checks['ox_message']    = 'Modul deaktiviert';
        }

        // 3. Letzte erfolgreiche Synchronisation
        $letzterSync = OxSyncLog::where('aktion', 'sync_complete')
            ->latest()
            ->first();
        $checks['letzter_sync']       = $letzterSync?->created_at?->toIso8601String();
        $checks['sync_alter_minuten'] = $letzterSync
            ? (int) now()->diffInMinutes($letzterSync->created_at)
            : null;

        // Sync älter als 1 Stunde → Warnung
        $checks['sync_veraltet'] = !$letzterSync
            || now()->diffInMinutes($letzterSync->created_at) > 60;

        // 4. Fehlerrate letzte 24h
        $fehler24h = OxSyncLog::where('aktion', 'error')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $checks['fehler_24h'] = $fehler24h;

        // Aufeinanderfolgende Fehler (letzte 5 Einträge)
        $letzte5 = OxSyncLog::whereIn('aktion', ['sync_complete', 'error'])
            ->latest()
            ->limit(5)
            ->pluck('aktion');

        $consecutiveErrors = 0;
        foreach ($letzte5 as $aktion) {
            if ($aktion === 'error') {
                $consecutiveErrors++;
            } else {
                break;
            }
        }
        $checks['aufeinanderfolgende_fehler'] = $consecutiveErrors;

        if ($consecutiveErrors >= 3) {
            $healthy = false;
        }

        // 5. Datenbank-Statistiken
        $checks['kalender_aktiv']  = OxCalendar::where('sichtbar', true)->count();
        $checks['kalender_gesamt'] = OxCalendar::withTrashed()->count();
        $checks['termine_gesamt']  = OxTermin::count();

        // 6. Gesamtergebnis
        $checks['status']    = $healthy ? 'healthy' : 'unhealthy';
        $checks['timestamp'] = now()->toIso8601String();

        return response()->json($checks, $healthy ? 200 : 503);
    }
}

