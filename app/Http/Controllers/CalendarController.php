<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOxTerminRequest;
use App\Http\Requests\UpdateOxTerminRequest;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use App\Models\UserIcalFeed;
use App\Services\OxCalendarService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class CalendarController extends Controller
{
    public function __construct(
        protected OxCalendarService $service
    ) {}

    /**
     * Kalender-Hauptansicht.
     */
    public function index(Request $request)
    {
        $user    = auth()->user();
        $kalender = $this->service->sichtbareKalender($user);

        // Standard-Ansicht aus Settings laden
        $defaultView = \App\Models\Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_default_ansicht')
            ->value('value') ?? 'timeGridWeek';

        // Schreibbare Kalender für den aktuellen User (Create-Operationen)
        $schreibbareKalender = $kalender->filter(
            fn ($cal) => $this->service->canWriteCalendar($user, $cal)
        );

        // Bearbeitbare Kalender für den aktuellen User (Edit/Move/Delete-Operationen)
        $bearbeitbareKalender = $kalender->filter(
            fn ($cal) => $this->service->canEditTermin($user, $cal)
        );

        // User-spezifische Farben aus DB laden (TODO 29)
        $userColors = $user->calendarColors()
            ->get()
            ->mapWithKeys(fn ($cal) => [$cal->id => $cal->pivot->farbe]);

        return view('calendar.index', [
            'kalender'            => $kalender,
            'schreibbareKalender' => $schreibbareKalender,
            'defaultView'         => $defaultView,
            'canCreate'           => $user->can('create calendar events') && $schreibbareKalender->isNotEmpty(),
            'canEdit'             => $user->can('edit calendar events') && $bearbeitbareKalender->isNotEmpty(),
            'feedToken'           => $user->calendar_token,
            'userColors'          => $userColors,
        ]);
    }

    /**
     * JSON-Endpoint für FullCalendar Event-Feed.
     * Query-Parameter: start, end, calendars (kommagetrennte IDs)
     */
    public function events(Request $request): JsonResponse
    {
        $user = auth()->user();
        $start = $request->query('start');
        $end   = $request->query('end');

        if (!$start || !$end) {
            return response()->json([]);
        }

        // Leere Kalender-Liste bedeutet: User hat alle Kalender deaktiviert
        $calendarsParam = $request->query('calendars', null);
        if ($calendarsParam === '') {
            return response()->json([]);
        }

        // Cache-Key basiert auf Zeitraum + User-ID + aktive Kalender (versioniert)
        $cacheKey = $this->service->eventsCacheKey(
            md5($start . $end . $user->id . ($calendarsParam ?? 'all'))
        );

        // Query-Parameter: ?expand_rrule=1 → serverseitige RRULE-Expansion (TODO 25)
        $expandRrule = $request->boolean('expand_rrule', false);

        // Bei expand_rrule eigenen Cache-Key verwenden, da Expansion andere Ergebnisse liefert
        if ($expandRrule) {
            $cacheKey = $this->service->eventsCacheKey(
                md5($start . $end . $user->id . ($calendarsParam ?? 'all') . '_expand')
            );
        }

        $events = Cache::remember($cacheKey, 300, function () use ($user, $start, $end, $calendarsParam, $expandRrule) {
            // Sichtbare Kalender-IDs ermitteln
            $sichtbareIds = $this->service->sichtbareKalender($user)->pluck('id');

            // null → kein Filter (Initialladung), sonst Schnittmenge mit sichtbaren
            if ($calendarsParam !== null) {
                $filterIds = collect(explode(',', $calendarsParam))
                    ->map(fn ($id) => (int) $id)
                    ->intersect($sichtbareIds);
            } else {
                $filterIds = $sichtbareIds;
            }

            // Termine laden
            $termine = OxTermin::whereIn('ox_calendar_id', $filterIds)
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('beginn', [$start, $end])
                        ->orWhere(function ($q) {
                            // Wiederkehrende Termine: RRULE vorhanden
                            $q->whereNotNull('rrule');
                        });
                })
                ->with('kalender')
                ->get();

            return $termine->flatMap(function (OxTermin $termin) use ($expandRrule, $start, $end) {
                $baseEvent = [
                    'id'     => $termin->id,
                    'title'  => $termin->titel,
                    'start'  => $termin->beginn->toIso8601String(),
                    'end'    => $termin->ende->toIso8601String(),
                    'allDay' => $termin->ganztaegig,
                    'color'  => $termin->kalender->farbe ?? '#3b82f6',
                    'extendedProps' => [
                        'terminId'     => $termin->id,
                        'calendarId'   => $termin->ox_calendar_id,
                        'calendarName' => $termin->kalender->name ?? '',
                        'ort'          => $termin->ort,
                        'beschreibung' => $termin->beschreibung,
                        'status'       => $termin->status,
                        'updatedAt'    => $termin->updated_at->toIso8601String(),
                    ],
                ];

                // RRULE-Handling
                if ($termin->rrule) {
                    if ($expandRrule) {
                        // Serverseitige Expansion: Einzeltermine zurückgeben (TODO 25)
                        $occurrences = $this->service->expandRruleTermine(
                            $termin,
                            \Carbon\Carbon::parse($start),
                            \Carbon\Carbon::parse($end)
                        );

                        return collect($occurrences)->map(function ($occ, $index) use ($termin, $baseEvent) {
                            return array_merge($baseEvent, [
                                'id'    => $termin->id . '_' . $index,
                                'start' => $occ['beginn']->toIso8601String(),
                                'end'   => $occ['ende']->toIso8601String(),
                                'extendedProps' => array_merge($baseEvent['extendedProps'], [
                                    'isOccurrence' => true,
                                ]),
                            ]);
                        });
                    }

                    // Clientseitige Expansion: RRULE an FullCalendar-Plugin übergeben
                    $baseEvent['editable'] = false; // Kein Drag-and-Drop für Wiederholungen
                    $baseEvent['rrule'] = 'DTSTART:' . $termin->beginn->format('Ymd\THis\Z') . "\nRRULE:" . $termin->rrule;

                    if ($termin->exdates) {
                        $baseEvent['exdate'] = $termin->exdates;
                    }

                    // duration statt end bei rrule-Events
                    $duration = $termin->beginn->diff($termin->ende);
                    $baseEvent['duration'] = sprintf('%02d:%02d', $duration->h, $duration->i);
                    unset($baseEvent['end']);
                }

                return [$baseEvent];
            })->values();
        });

        // User-spezifische iCal-Feeds laden und Events anhängen (TODO 30)
        $icalFeeds = $user->icalFeeds()->where('aktiv', true)->get();
        if ($icalFeeds->isNotEmpty()) {
            $feedEvents = $icalFeeds->flatMap(
                fn ($feed) => $this->service->fetchIcalFeed($feed, $start, $end)
            );
            $events = collect($events)->concat($feedEvents)->values();
        }

        return response()->json($events);
    }

    // ========================================================================
    // iCal-Feed-Verwaltung (TODO 30)
    // ========================================================================

    /**
     * Neuen iCal-Feed abonnieren.
     */
    public function storeIcalFeed(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'url'   => ['required', 'url', 'max:2000'],
            'farbe' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // URL validieren: Feed muss erreichbar und gültiges iCal sein
        try {
            $response = Http::timeout(5)->get($validated['url']);
            if (!$response->successful()) {
                return redirectBack('danger', 'Feed-URL nicht erreichbar (HTTP ' . $response->status() . ').');
            }
            Reader::read($response->body());
        } catch (\Exception $e) {
            return redirectBack('danger', 'Kein gültiger iCal-Feed: ' . $e->getMessage());
        }

        $user = auth()->user();

        if ($user->icalFeeds()->count() >= 10) {
            return redirectBack('warning', 'Maximal 10 iCal-Feeds erlaubt.');
        }

        $user->icalFeeds()->create($validated);

        return redirectBack('success', "Feed \"{$validated['name']}\" wurde hinzugefügt.");
    }

    /**
     * iCal-Feed aktualisieren.
     */
    public function updateIcalFeed(Request $request, UserIcalFeed $feed)
    {
        if ($feed->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'url'   => ['required', 'url', 'max:2000'],
            'farbe' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'aktiv' => ['boolean'],
        ]);

        $feed->update($validated);

        return redirectBack('success', "Feed \"{$feed->name}\" aktualisiert.");
    }

    /**
     * iCal-Feed entfernen.
     */
    public function destroyIcalFeed(UserIcalFeed $feed)
    {
        if ($feed->user_id !== auth()->id()) {
            abort(403);
        }

        $name = $feed->name;
        $feed->delete();

        return redirectBack('success', "Feed \"{$name}\" wurde entfernt.");
    }

    // ========================================================================
    // Termin-Detail
    // ========================================================================

    /**
     * Termin-Detail (JSON für Modal).
        $user = auth()->user();

        // Sichtbarkeitsprüfung
        $sichtbareIds = $this->service->sichtbareKalender($user)->pluck('id');
        if (!$sichtbareIds->contains($termin->ox_calendar_id)) {
            abort(403, 'Keine Berechtigung für diesen Kalender.');
        }

        $termin->load(['kalender', 'teilnehmer', 'ersteller']);

        return response()->json([
            'id'           => $termin->id,
            'titel'        => $termin->titel,
            'beschreibung' => $termin->beschreibung,
            'ort'          => $termin->ort,
            'beginn'       => $termin->beginn->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'ende'         => $termin->ende->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'beginn_iso'   => $termin->beginn->toIso8601String(),
            'ende_iso'     => $termin->ende->toIso8601String(),
            'ganztaegig'   => $termin->ganztaegig,
            'status'       => $termin->status,
            'rrule'        => $termin->rrule,
            'kalender'     => [
                'id'    => $termin->kalender->id,
                'name'  => $termin->kalender->name,
                'farbe' => $termin->kalender->farbe,
            ],
            'teilnehmer' => $termin->teilnehmer->map(fn ($t) => [
                'name'   => $t->name,
                'email'  => $t->email,
                'status' => $t->status,
            ]),
            'ersteller' => $termin->ersteller ? [
                'id'   => $termin->ersteller->id,
                'name' => $termin->ersteller->name,
            ] : null,
            'can_edit'   => $user->can('edit calendar events')
                && $this->service->canWriteCalendar($user, $termin->kalender),
            'updated_at' => $termin->updated_at->toIso8601String(),
        ]);
    }

    // ========================================================================
    // iCal-Feed
    // ========================================================================

    /**
     * Persönlicher iCal-Feed (Token-geschützt, kein Auth-Middleware).
     * Liefert alle sichtbaren Termine des Users als VCALENDAR.
     */
    public function feed(string $token)
    {
        $user = User::where('calendar_token', $token)->firstOrFail();

        $sichtbareIds = $this->service->sichtbareKalender($user)->pluck('id');

        $termine = OxTermin::whereIn('ox_calendar_id', $sichtbareIds)
            ->where('beginn', '>=', now()->subMonths(3))
            ->where('beginn', '<=', now()->addMonths(6))
            ->with('kalender')
            ->orderBy('beginn')
            ->get();

        $vcalendar = new VCalendar();
        $vcalendar->PRODID = '-//MitarbeiterBoard//Kalender//DE';
        $vcalendar->{'X-WR-CALNAME'} = 'MitarbeiterBoard Kalender';

        foreach ($termine as $termin) {
            $vevent = $vcalendar->add('VEVENT', [
                'UID'     => $termin->ox_uid,
                'SUMMARY' => $termin->titel,
                'DTSTART' => $termin->beginn->setTimezone('Europe/Berlin'),
                'DTEND'   => $termin->ende->setTimezone('Europe/Berlin'),
            ]);

            if ($termin->beschreibung) {
                $vevent->DESCRIPTION = $termin->beschreibung;
            }
            if ($termin->ort) {
                $vevent->LOCATION = $termin->ort;
            }
            if ($termin->status) {
                $vevent->STATUS = $termin->status;
            }
            if ($termin->rrule) {
                $vevent->RRULE = $termin->rrule;
            }
            if ($termin->ganztaegig) {
                $vevent->DTSTART['VALUE'] = 'DATE';
                $vevent->DTEND['VALUE']   = 'DATE';
            }
        }

        return response($vcalendar->serialize(), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="kalender.ics"',
        ]);
    }

    /**
     * Token für iCal-Feed generieren/erneuern.
     */
    public function generateFeedToken(Request $request)
    {
        $user = auth()->user();
        $user->update(['calendar_token' => Str::random(64)]);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Neuer Kalender-Feed-Token wurde generiert.',
        ]);
    }

    // ========================================================================
    // Schreibende Methoden
    // ========================================================================

    /**
     * Termin per Drag-and-Drop verschieben oder per Resize verlängern (AJAX).
     *
     * Leichtgewichtiger PATCH-Endpoint: Erwartet nur neue Zeiten + Optimistic-Lock-Token.
     * Wiederkehrende Termine (RRULE) werden abgelehnt.
     */
    public function move(Request $request, OxTermin $termin): JsonResponse
    {
        // 1. RRULE-Termine ablehnen (vor teuren DB-Queries)
        if ($termin->rrule) {
            return response()->json([
                'error' => 'Wiederkehrende Termine können nicht per Drag-and-Drop verschoben werden.',
            ], 422);
        }

        // 2. Request validieren
        $validated = $request->validate([
            'beginn'              => ['required', 'date'],
            'ende'                => ['required', 'date', 'after_or_equal:beginn'],
            'ganztaegig'          => ['boolean'],
            'expected_updated_at' => ['required', 'date'],
        ]);

        // 3. Kalender + Gruppen laden und Schreibberechtigung prüfen
        $termin->load('kalender.groups');
        $user = auth()->user();

        if (!$this->service->canEditTermin($user, $termin->kalender)) {
            return response()->json(['error' => 'Keine Schreibberechtigung für diesen Kalender.'], 403);
        }

        // 4. Optimistic Locking: Konflikt erkennen
        $expectedUpdatedAt = \Carbon\Carbon::parse($validated['expected_updated_at']);
        if ($termin->updated_at->ne($expectedUpdatedAt)) {
            return response()->json([
                'error'  => 'Der Termin wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
                'reload' => true,
            ], 409);
        }

        // 5. Update durchführen
        try {
            $updateData = [
                'titel'        => $termin->titel,
                'beschreibung' => $termin->beschreibung,
                'ort'          => $termin->ort,
                'status'       => $termin->status,
                'rrule'        => null,
                'beginn'       => $validated['beginn'],
                'ende'         => $validated['ende'],
                'ganztaegig'   => $validated['ganztaegig'] ?? $termin->ganztaegig,
            ];

            // Nur via OX-Service updaten wenn OX konfiguriert ist UND der Termin einen CalDAV-href hat
            if ($this->service->isEnabled() && !empty($termin->ox_href)) {
                $this->service->updateTermin($termin, $updateData);
            } else {
                // Lokales Update ohne OX-Sync (Termin ohne CalDAV-Anbindung oder OX deaktiviert)
                $termin->update([
                    'beginn'    => $updateData['beginn'],
                    'ende'      => $updateData['ende'],
                    'ganztaegig' => $updateData['ganztaegig'],
                ]);
                $this->service->invalidateEventsCache($termin->ox_calendar_id);
            }

            return response()->json([
                'success'    => true,
                'message'    => "Termin \"{$termin->titel}\" verschoben.",
                'updated_at' => $termin->fresh()->updated_at->toIso8601String(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'Fehler beim Verschieben: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Neuen Termin erstellen (→ OX).
     */
    public function store(StoreOxTerminRequest $request)
    {
        $calendar = OxCalendar::findOrFail($request->validated()['ox_calendar_id']);
        $user     = auth()->user();

        // Gruppen-basierte Schreibberechtigung prüfen
        if (!$this->service->canWriteCalendar($user, $calendar)) {
            abort(403, 'Keine Schreibberechtigung für diesen Kalender.');
        }

        try {
            $termin = $this->service->createTermin($calendar, $request->validated());

            return redirectBack('success', "Termin \"{$termin->titel}\" wurde erstellt.");
        } catch (\RuntimeException $e) {
            return redirectBack('danger', 'Fehler beim Erstellen des Termins: ' . $e->getMessage());
        }
    }

    /**
     * Termin bearbeiten (→ OX).
     * Mit Optimistic Locking über expected_updated_at.
     */
    public function update(UpdateOxTerminRequest $request, OxTermin $termin)
    {
        $user = auth()->user();

        // Gruppen-basierte Schreibberechtigung prüfen
        if (!$this->service->canWriteCalendar($user, $termin->kalender)) {
            abort(403, 'Keine Schreibberechtigung für diesen Kalender.');
        }

        // Optimistic Locking: Prüfe ob Termin zwischenzeitlich geändert wurde
        $expectedUpdatedAt = \Carbon\Carbon::parse($request->validated()['expected_updated_at']);
        if ($termin->updated_at->ne($expectedUpdatedAt)) {
            return back()->withInput()->with([
                'type'    => 'warning',
                'Meldung' => 'Der Termin wurde zwischenzeitlich geändert. Bitte Änderungen prüfen und erneut speichern.',
            ]);
        }

        try {
            $this->service->updateTermin($termin, $request->validated());

            return redirectBack('success', "Termin \"{$termin->titel}\" wurde aktualisiert.");
        } catch (\RuntimeException $e) {
            return redirectBack('danger', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    /**
     * Termin löschen (→ OX).
     */
    public function destroy(OxTermin $termin)
    {
        $user = auth()->user();

        if (!$user->can('edit calendar events') || !$this->service->canWriteCalendar($user, $termin->kalender)) {
            abort(403, 'Keine Berechtigung zum Löschen.');
        }

        try {
            $titel = $termin->titel;
            $this->service->deleteTermin($termin);

            return redirectBack('success', "Termin \"{$titel}\" wurde gelöscht.");
        } catch (\RuntimeException $e) {
            return redirectBack('danger', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // Kalenderfarben (TODO 29) – Hybrid localStorage/DB
    // ========================================================================

    /**
     * Alle benutzerdefinierten Farben des aktuellen Users (JSON).
     */
    public function getColors(): JsonResponse
    {
        $user   = auth()->user();
        $colors = $user->calendarColors()
            ->get()
            ->mapWithKeys(fn ($cal) => [$cal->id => $cal->pivot->farbe]);

        return response()->json($colors);
    }

    /**
     * Farben als Batch speichern (sync).
     * Body: { "farben": { "<calendarId>": "#rrggbb", … } }
     */
    public function saveColors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farben'   => ['required', 'array', 'max:50'],
            'farben.*' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $user = auth()->user();

        $syncData = [];
        foreach ($validated['farben'] as $calendarId => $farbe) {
            $calendarId = (int) $calendarId;
            if (OxCalendar::where('id', $calendarId)->exists()) {
                $syncData[$calendarId] = ['farbe' => $farbe];
            }
        }

        $user->calendarColors()->sync($syncData);

        return response()->json(['success' => true, 'count' => count($syncData)]);
    }

    /**
     * Benutzerdefinierte Farbe für einen einzelnen Kalender zurücksetzen.
     */
    public function resetColor(OxCalendar $oxCalendar): JsonResponse
    {
        auth()->user()->calendarColors()->detach($oxCalendar->id);

        return response()->json(['success' => true]);
    }

    // ========================================================================
    // PDF-Export (TODO 28)
    // ========================================================================

    /**
     * PDF-Export der Kalender-Wochenansicht.
     *
     * Query-Parameter:
     * - date:      Startdatum der Woche (default: aktuelle Woche)
     * - calendars: Kommagetrennte Kalender-IDs (default: alle sichtbaren)
     */
    public function exportPdf(Request $request)
    {
        $user = auth()->user();

        $date = $request->query('date')
            ? \Carbon\Carbon::parse($request->query('date'))->startOfWeek()
            : now()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        $sichtbare        = $this->service->sichtbareKalender($user);
        $calendarsParam   = $request->query('calendars');
        if ($calendarsParam) {
            $filterIds = collect(explode(',', $calendarsParam))->map(fn ($id) => (int) $id);
            $sichtbare = $sichtbare->filter(fn ($cal) => $filterIds->contains($cal->id));
        }

        $calendarIds = $sichtbare->pluck('id');

        $termine = OxTermin::whereIn('ox_calendar_id', $calendarIds)
            ->where(function ($q) use ($date, $weekEnd) {
                $q->whereBetween('beginn', [$date, $weekEnd])
                  ->orWhere(function ($inner) use ($date, $weekEnd) {
                      $inner->where('beginn', '<=', $weekEnd)
                            ->where('ende', '>=', $date);
                  });
            })
            ->whereNull('rrule')
            ->with('kalender')
            ->orderBy('beginn')
            ->get();

        $tage = collect();
        for ($d = $date->copy(); $d->lte($weekEnd); $d->addDay()) {
            $tagTermine = $termine->filter(fn ($t) => $t->beginn->isSameDay($d));
            $tage->push([
                'datum'   => $d->copy(),
                'label'   => $d->translatedFormat('l, d.m.'),
                'termine' => $tagTermine->sortBy('beginn'),
            ]);
        }

        $pdf = Pdf::loadView('calendar.export.woche-pdf', [
            'tage'     => $tage,
            'kalender' => $sichtbare,
            'woche'    => $date->format('d.m.') . ' – ' . $weekEnd->format('d.m.Y'),
            'kw'       => $date->isoWeek(),
        ]);

        $pdf->setPaper('a4', 'landscape');

        $filename = 'Kalender_KW' . $date->isoWeek() . '_' . $date->format('Y') . '.pdf';

        return $pdf->download($filename);
    }

    // ========================================================================
    // Suche (TODO 27)
    // ========================================================================

    /**
     * Volltextsuche über Termine (AJAX).
     *
     * Query-Parameter:
     * - q: Suchbegriff (min. 2 Zeichen)
     * - limit: Max. Ergebnisse (default 20, max 50)
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $limit = min((int) $request->query('limit', 20), 50);
        $user  = auth()->user();

        $sichtbareIds = $this->service->sichtbareKalender($user)->pluck('id');

        // SQL-Sonderzeichen escapen (%, _)
        $escapedQuery = str_replace(['%', '_'], ['\%', '\_'], $query);

        $termine = OxTermin::whereIn('ox_calendar_id', $sichtbareIds)
            ->where(function ($q) use ($escapedQuery) {
                $q->where('titel', 'LIKE', "%{$escapedQuery}%")
                  ->orWhere('beschreibung', 'LIKE', "%{$escapedQuery}%")
                  ->orWhere('ort', 'LIKE', "%{$escapedQuery}%");
            })
            ->with('kalender')
            ->orderByDesc('beginn')
            ->limit($limit)
            ->get();

        return response()->json(
            $termine->map(fn (OxTermin $t) => [
                'id'         => $t->id,
                'titel'      => $t->titel,
                'ort'        => $t->ort,
                'beginn'     => $t->beginn->timezone('Europe/Berlin')->format('d.m.Y H:i'),
                'ende'       => $t->ende->timezone('Europe/Berlin')->format('d.m.Y H:i'),
                'beginn_iso' => $t->beginn->toIso8601String(),
                'ganztaegig' => $t->ganztaegig,
                'kalender'   => [
                    'name'  => $t->kalender->name ?? '',
                    'farbe' => $t->kalender->farbe ?? '#3b82f6',
                ],
                'snippet' => $t->beschreibung
                    ? Str::limit(strip_tags($t->beschreibung), 100)
                    : null,
            ])
        );
    }
}

