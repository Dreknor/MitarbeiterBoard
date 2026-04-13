<?php

namespace App\Http\Controllers;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use App\Models\UserIcalFeed;
use App\Services\OxCalendarService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sabre\VObject\Reader;

class CalendarController extends Controller
{
    /**
     * Kalender-Hauptansicht.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $kalender = $this->sichtbareKalender($user);

        // Standard-Ansicht aus Settings laden
        $defaultView = \App\Models\Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_default_ansicht')
            ->value('value') ?? 'timeGridWeek';

        // Schreibbare Kalender für den aktuellen User
        $schreibbareKalender = $kalender->filter(function ($cal) use ($user) {
            return $this->canWriteCalendar($user, $cal);
        });

        // Persönliche iCal-Feeds des Users
        $icalFeeds = $user->icalFeeds()->where('aktiv', true)->get();

        return view('calendar.index', [
            'kalender'            => $kalender,
            'icalFeeds'           => $icalFeeds,
            'schreibbareKalender' => $schreibbareKalender,
            'defaultView'         => $defaultView,
            'canCreate'           => $user->can('create calendar events') && $schreibbareKalender->isNotEmpty(),
            'canEdit'             => $schreibbareKalender->isNotEmpty(),
            'userColors'          => [],
        ]);
    }

    /**
     * JSON-Endpoint für FullCalendar Event-Feed.
     * Query-Parameter: start, end, calendars (kommagetrennte IDs)
     */
    public function events(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $start  = $request->query('start');
        $end    = $request->query('end');

        if (!$start || !$end) {
            return response()->json([]);
        }

        $calendarsParam = $request->query('calendars', '');
        $allParams      = $calendarsParam !== '' ? explode(',', $calendarsParam) : [];

        // ── IDs trennen: OxCalendar-IDs (numerisch) vs. iCal-Feed-IDs (ical_X) ──
        $oxIds   = collect($allParams)
            ->filter(fn ($id) => !Str::startsWith(trim($id), 'ical_'))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values();

        $icalIds = collect($allParams)
            ->filter(fn ($id) => Str::startsWith(trim($id), 'ical_'))
            ->map(fn ($id) => (int) Str::after(trim($id), 'ical_'))
            ->filter()
            ->values();

        // ── OxTermin-Events (gecacht) ────────────────────────────────────────────
        $service    = app(OxCalendarService::class);
        $oxCacheKey = $service->eventsCacheKey(md5($start . $end . $user->id . $oxIds->sort()->join(',')));

        $oxEvents = Cache::remember($oxCacheKey, 300, function () use ($user, $start, $end, $oxIds) {
            $sichtbareIds = $this->sichtbareKalender($user)->pluck('id');

            if ($oxIds->isNotEmpty()) {
                $filterIds = $oxIds->intersect($sichtbareIds);
            } else {
                $filterIds = $sichtbareIds;
            }

            $termine = OxTermin::whereIn('ox_calendar_id', $filterIds)
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('beginn', [$start, $end])
                        ->orWhereNotNull('rrule');
                })
                ->with('kalender')
                ->get();

            return $termine->map(function (OxTermin $termin) {
                $event = [
                    'id'            => $termin->id,
                    'title'         => $termin->titel,
                    'start'         => $termin->beginn->toIso8601String(),
                    'end'           => $termin->ende->toIso8601String(),
                    'allDay'        => $termin->ganztaegig,
                    'color'         => $termin->kalender->farbe ?? '#3b82f6',
                    'extendedProps' => [
                        'terminId'     => $termin->id,
                        'calendarId'   => $termin->ox_calendar_id,
                        'calendarName' => $termin->kalender->name ?? '',
                        'ort'          => $termin->ort,
                        'beschreibung' => $termin->beschreibung,
                        'status'       => $termin->status,
                        'updatedAt'    => $termin->updated_at?->toIso8601String(),
                    ],
                ];

                if ($termin->rrule) {
                    $event['rrule'] = 'DTSTART:' . $termin->beginn->format('Ymd\THis\Z') . "\nRRULE:" . $termin->rrule;
                    if ($termin->exdates) {
                        $event['exdate'] = $termin->exdates;
                    }
                    $duration = $termin->beginn->diff($termin->ende);
                    $event['duration'] = sprintf('%02d:%02d', $duration->h, $duration->i);
                    unset($event['end']);
                    $event['editable'] = false;
                }

                return $event;
            })->values();
        });

        // ── iCal-Feed-Events ─────────────────────────────────────────────────────
        $icalEvents = collect();

        if ($icalIds->isNotEmpty()) {
            // Nur eigene, aktive Feeds des Users laden
            $feeds = UserIcalFeed::whereIn('id', $icalIds)
                ->where('user_id', $user->id)
                ->where('aktiv', true)
                ->get();

            foreach ($feeds as $feed) {
                $icalEvents = $icalEvents->concat($this->fetchIcalFeedEvents($feed, $start, $end));
            }
        } elseif ($calendarsParam === '') {
            // Kein Filter angegeben → alle aktiven eigenen Feeds einbeziehen
            $feeds = UserIcalFeed::where('user_id', $user->id)->where('aktiv', true)->get();
            foreach ($feeds as $feed) {
                $icalEvents = $icalEvents->concat($this->fetchIcalFeedEvents($feed, $start, $end));
            }
        }

        return response()->json($oxEvents->concat($icalEvents)->values());
    }

    /**
     * Termin-Detail (JSON für Modal).
     */
    public function show(OxTermin $termin): JsonResponse
    {
        $user = auth()->user();

        $sichtbareIds = $this->sichtbareKalender($user)->pluck('id');
        if (!$sichtbareIds->contains($termin->ox_calendar_id)) {
            abort(403, 'Keine Berechtigung für diesen Kalender.');
        }

        $termin->load(['kalender', 'teilnehmer', 'ersteller']);

        return response()->json([
            'id'          => $termin->id,
            'titel'       => $termin->titel,
            'beschreibung' => $termin->beschreibung,
            'ort'         => $termin->ort,
            'beginn'      => $termin->beginn->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'ende'        => $termin->ende->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'beginn_iso'  => $termin->beginn->toIso8601String(),
            'ende_iso'    => $termin->ende->toIso8601String(),
            'ganztaegig'  => $termin->ganztaegig,
            'status'      => $termin->status,
            'rrule'       => $termin->rrule,
            'kalender'    => [
                'id'    => $termin->kalender->id,
                'name'  => $termin->kalender->name,
                'farbe' => $termin->kalender->farbe,
            ],
            'teilnehmer'  => $termin->teilnehmer->map(fn ($t) => [
                'name'   => $t->name,
                'email'  => $t->email,
                'status' => $t->status,
            ]),
            'ersteller'   => $termin->ersteller ? [
                'id'   => $termin->ersteller->id,
                'name' => $termin->ersteller->name,
            ] : null,
            'can_edit'    => $user->can('edit calendar events')
                && $this->canWriteCalendar($user, $termin->kalender),
            'updated_at'  => $termin->updated_at->toIso8601String(),
        ]);
    }

    /**
     * PDF-Export der Kalender-Wochenansicht.
     *
     * Query-Parameter:
     * - date:      Startdatum der Woche (default: aktuelle Woche)
     * - calendars: Kommagetrennte Kalender-IDs (default: alle sichtbaren)
     *
     * Enthält ALLE Termine der Woche – auch wiederkehrende (RRULE) –
     * damit das PDF dieselben 18 Einträge wie die Wochenansicht zeigt.
     */
    public function exportPdf(Request $request)
    {
        $user    = auth()->user();
        $service = app(OxCalendarService::class);

        // Woche ermitteln
        $date    = $request->query('date')
            ? Carbon::parse($request->query('date'))->startOfWeek()
            : now()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        // Sichtbare Kalender filtern
        $sichtbare      = $this->sichtbareKalender($user);
        $calendarsParam = $request->query('calendars');
        if ($calendarsParam) {
            $filterIds  = collect(explode(',', $calendarsParam))->map(fn ($id) => (int) $id);
            $sichtbare  = $sichtbare->filter(fn ($cal) => $filterIds->contains($cal->id));
        }
        $calendarIds = $sichtbare->pluck('id');

        // ── 1. Einfache Termine (kein RRULE) im Zeitfenster ──────────────────
        $einfacheTermine = OxTermin::whereIn('ox_calendar_id', $calendarIds)
            ->whereBetween('beginn', [$date, $weekEnd])
            ->whereNull('rrule')
            ->with('kalender')
            ->orderBy('beginn')
            ->get();

        // ── 2. Wiederkehrende Termine (RRULE) – Vorkommen im Zeitfenster ─────
        //    Wir laden alle RRULE-Termine dieser Kalender und expandieren sie
        //    mit sabre/vobject auf das Wochenfenster.
        $rruleTermine = OxTermin::whereIn('ox_calendar_id', $calendarIds)
            ->whereNotNull('rrule')
            ->with('kalender')
            ->get();

        /** @var Collection $expandierteTermine
         *  Jedes Element: ['termin' => OxTermin, 'beginn' => Carbon, 'ende' => Carbon]
         */
        $expandierteTermine = collect();
        foreach ($rruleTermine as $termin) {
            $vorkommen = $this->expandRruleInWoche($termin, $date, $weekEnd);
            foreach ($vorkommen as $occurrence) {
                $expandierteTermine->push([
                    'termin' => $termin,
                    'beginn' => $occurrence['beginn'],
                    'ende'   => $occurrence['ende'],
                ]);
            }
        }

        // ── 3. Pro Wochentag gruppieren ───────────────────────────────────────
        $tage = collect();
        for ($d = $date->copy(); $d->lte($weekEnd); $d->addDay()) {
            $tagKopie = $d->copy();

            // Einfache Termine des Tages
            $tagEinfach = $einfacheTermine
                ->filter(fn ($t) => $t->beginn->isSameDay($tagKopie))
                ->map(fn ($t) => (object)[
                    'beginn'     => $t->beginn,
                    'ende'       => $t->ende,
                    'ganztaegig' => $t->ganztaegig,
                    'titel'      => $t->titel,
                    'ort'        => $t->ort,
                    'kalender'   => $t->kalender,
                ])->toBase();

            // RRULE-Vorkommen des Tages
            $tagRrule = $expandierteTermine
                ->filter(fn ($item) => $item['beginn']->isSameDay($tagKopie))
                ->map(fn ($item) => (object)[
                    'beginn'     => $item['beginn'],
                    'ende'       => $item['ende'],
                    'ganztaegig' => $item['termin']->ganztaegig,
                    'titel'      => $item['termin']->titel,
                    'ort'        => $item['termin']->ort,
                    'kalender'   => $item['termin']->kalender,
                ]);

            // Zusammenführen und nach Uhrzeit sortieren
            $alleTermine = $tagEinfach->merge($tagRrule)->sortBy('beginn')->values();

            $tage->push([
                'datum'   => $tagKopie,
                'label'   => $tagKopie->translatedFormat('l, d.m.'),
                'termine' => $alleTermine,
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

    // =========================================================================
    // Öffentlicher iCal-Feed (Token-geschützt)
    // =========================================================================

    /**
     * Persönlichen iCal-Feed ausgeben (token-geschützt, kein Auth-Middleware).
     */
    public function feed(string $token)
    {
        $setting = \App\Models\Setting::where('module', 'Kalender')
            ->where('setting', 'like', 'feed_token_%')
            ->where('value', $token)
            ->first();

        abort_if(!$setting, 404, 'Ungültiger Feed-Token.');

        $userId = (int) str_replace('feed_token_', '', $setting->setting);
        $user   = \App\Models\User::findOrFail($userId);

        $kalender = $this->sichtbareKalender($user);

        $termine = OxTermin::whereIn('ox_calendar_id', $kalender->pluck('id'))
            ->where('beginn', '>=', now()->subYear())
            ->where('beginn', '<=', now()->addYear())
            ->orderBy('beginn')
            ->get();

        $lines = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//ESZ Radebeul//Kalender//DE", "CALSCALE:GREGORIAN"];
        foreach ($termine as $t) {
            $lines[] = "BEGIN:VEVENT";
            $lines[] = "UID:" . ($t->ox_uid ?: "termin-{$t->id}@esz-radebeul.de");
            $lines[] = "SUMMARY:" . str_replace(["\r", "\n"], " ", $t->titel ?? '');
            $lines[] = "DTSTART:" . $t->beginn->utc()->format('Ymd\THis\Z');
            $lines[] = "DTEND:"   . $t->ende->utc()->format('Ymd\THis\Z');
            if ($t->ort)          $lines[] = "LOCATION:" . str_replace(["\r", "\n"], " ", $t->ort);
            if ($t->beschreibung) $lines[] = "DESCRIPTION:" . str_replace(["\r", "\n"], " ", $t->beschreibung);
            if ($t->rrule)        $lines[] = "RRULE:" . $t->rrule;
            $lines[] = "END:VEVENT";
        }
        $lines[] = "END:VCALENDAR";

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="kalender.ics"',
        ]);
    }

    // =========================================================================
    // Termin-Suche
    // =========================================================================

    /**
     * Volltext-Suche über Termine (für die Sidebar-Suchleiste).
     */
    public function search(Request $request): JsonResponse
    {
        $q    = trim($request->query('q', ''));
        $user = auth()->user();

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $sichtbareIds = $this->sichtbareKalender($user)->pluck('id');

        $treffer = OxTermin::whereIn('ox_calendar_id', $sichtbareIds)
            ->where(function ($query) use ($q) {
                $query->where('titel', 'like', "%{$q}%")
                    ->orWhere('ort', 'like', "%{$q}%")
                    ->orWhere('beschreibung', 'like', "%{$q}%");
            })
            ->with('kalender')
            ->orderBy('beginn', 'desc')
            ->limit(20)
            ->get();

        return response()->json($treffer->map(fn ($t) => [
            'id'        => $t->id,
            'titel'     => $t->titel,
            'ort'       => $t->ort,
            'beginn'    => $t->beginn->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'beginn_raw' => $t->beginn->toIso8601String(),
            'kalender'  => ['id' => $t->ox_calendar_id, 'name' => $t->kalender?->name, 'farbe' => $t->kalender?->farbe],
        ]));
    }

    // =========================================================================
    // iCal-Feed-Verwaltung
    // =========================================================================

    /**
     * Persönlichen Feed-Token generieren / erneuern.
     */
    public function generateFeedToken(Request $request)
    {
        $user  = auth()->user();
        $token = bin2hex(random_bytes(32));

        \App\Models\Setting::updateOrCreate(
            ['module' => 'Kalender', 'setting' => 'feed_token_' . $user->id],
            ['value'  => $token]
        );

        return redirectBack('success', 'Feed-Token wurde generiert.');
    }

    /**
     * iCal-Feed abonnieren.
     */
    public function storeIcalFeed(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'url'   => 'required|url|max:2000',
            'farbe' => 'nullable|string|max:7',
        ]);

        auth()->user()->icalFeeds()->create([
            'name'  => $validated['name'],
            'url'   => $validated['url'],
            'farbe' => $validated['farbe'] ?? '#6366f1',
            'aktiv' => true,
        ]);

        return redirectBack('success', 'Feed wurde abonniert.');
    }

    /**
     * iCal-Feed aktualisieren.
     */
    public function updateIcalFeed(Request $request, \App\Models\UserIcalFeed $feed)
    {
        abort_if($feed->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'url'   => 'required|url|max:2000',
            'farbe' => 'nullable|string|max:7',
            'aktiv' => 'boolean',
        ]);

        $feed->update($validated);

        return redirectBack('success', 'Feed wurde aktualisiert.');
    }

    /**
     * iCal-Feed entfernen.
     */
    public function destroyIcalFeed(\App\Models\UserIcalFeed $feed)
    {
        abort_if($feed->user_id !== auth()->id(), 403);
        $feed->delete();

        return redirectBack('success', 'Feed wurde entfernt.');
    }

    // =========================================================================
    // Benutzer-spezifische Kalenderfarben
    // =========================================================================

    public function getColors(): JsonResponse
    {
        $userId = auth()->id();
        $colors = \App\Models\Setting::where('module', 'Kalender')
            ->where('setting', 'like', "user_color_{$userId}_%")
            ->pluck('value', 'setting')
            ->mapWithKeys(fn ($v, $k) => [str_replace("user_color_{$userId}_", '', $k) => $v]);

        return response()->json($colors);
    }

    public function saveColors(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $colors = $request->validate(['colors' => 'required|array'])['colors'];

        foreach ($colors as $calId => $color) {
            \App\Models\Setting::updateOrCreate(
                ['module' => 'Kalender', 'setting' => "user_color_{$userId}_{$calId}"],
                ['value'  => $color]
            );
        }

        return response()->json(['ok' => true]);
    }

    public function resetColor(OxCalendar $oxCalendar): JsonResponse
    {
        $userId = auth()->id();
        \App\Models\Setting::where('module', 'Kalender')
            ->where('setting', "user_color_{$userId}_{$oxCalendar->id}")
            ->delete();

        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // Termin-CRUD (Schreiben)
    // =========================================================================

    /**
     * Neuen Termin anlegen.
     */
    public function store(Request $request)
    {
        $user    = auth()->user();
        $service = app(OxCalendarService::class);

        $validated = $request->validate([
            'ox_calendar_id' => 'required|integer|exists:ox_calendars,id',
            'titel'          => 'required|string|max:255',
            'beginn'         => 'required|date',
            'ende'           => 'required|date|after_or_equal:beginn',
            'ort'            => 'nullable|string|max:255',
            'beschreibung'   => 'nullable|string|max:5000',
            'ganztaegig'     => 'boolean',
            'rrule'          => 'nullable|string|max:500',
        ]);

        $kalender = OxCalendar::findOrFail($validated['ox_calendar_id']);

        if (!$service->canWriteCalendar($user, $kalender)) {
            abort(403, 'Keine Schreibberechtigung für diesen Kalender.');
        }

        try {
            $termin = $service->createTermin($kalender, array_merge($validated, [
                'ganztaegig' => $request->boolean('ganztaegig'),
            ]));

            return redirectBack('success', 'Termin "' . $termin->titel . '" wurde angelegt.');
        } catch (\RuntimeException $e) {
            Log::warning('Termin erstellen fehlgeschlagen', ['error' => $e->getMessage()]);
            return redirectBack('danger', 'Termin konnte nicht angelegt werden: ' . $e->getMessage());
        }
    }

    /**
     * Termin aktualisieren (mit Optimistic Locking via expected_updated_at).
     */
    public function update(Request $request, OxTermin $termin)
    {
        $user    = auth()->user();
        $service = app(OxCalendarService::class);
        $termin->load('kalender');

        if (!$service->canEditTermin($user, $termin->kalender)) {
            abort(403, 'Keine Schreibberechtigung für diesen Kalender.');
        }

        // Optimistic Locking
        if ($request->filled('expected_updated_at')
            && $termin->updated_at->toIso8601String() !== $request->input('expected_updated_at')) {
            return redirectBack(
                'warning',
                'Der Termin wurde zwischenzeitlich geändert. Bitte neu laden.'
            );
        }

        $validated = $request->validate([
            'titel'        => 'required|string|max:255',
            'beginn'       => 'required|date',
            'ende'         => 'required|date|after_or_equal:beginn',
            'ort'          => 'nullable|string|max:255',
            'beschreibung' => 'nullable|string|max:5000',
            'ganztaegig'   => 'boolean',
            'rrule'        => 'nullable|string|max:500',
        ]);

        try {
            $service->updateTermin($termin, array_merge($validated, [
                'ganztaegig' => $request->boolean('ganztaegig'),
            ]));

            return redirectBack('success', 'Termin "' . $termin->titel . '" wurde aktualisiert.');
        } catch (\RuntimeException $e) {
            Log::warning('Termin bearbeiten fehlgeschlagen', ['error' => $e->getMessage()]);
            return redirectBack('danger', 'Termin konnte nicht aktualisiert werden: ' . $e->getMessage());
        }
    }

    /**
     * Termin verschieben (Drag & Drop, AJAX PATCH).
     */
    public function move(Request $request, OxTermin $termin): JsonResponse
    {
        $user    = auth()->user();
        $service = app(OxCalendarService::class);
        $termin->load('kalender');

        if (!$service->canEditTermin($user, $termin->kalender)) {
            abort(403);
        }

        // RRULE-Termine können nicht per Drag & Drop verschoben werden
        if ($termin->rrule) {
            return response()->json([
                'error' => 'Wiederkehrende Termine können nicht per Drag-and-Drop verschoben werden.',
            ], 422);
        }

        $validated = $request->validate([
            'beginn'              => 'required|date',
            'ende'                => 'required|date|after_or_equal:beginn',
            'expected_updated_at' => 'required|string',
        ]);

        // Optimistic Locking
        if ($termin->updated_at->toIso8601String() !== $validated['expected_updated_at']) {
            return response()->json([
                'error'  => 'Der Termin wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
                'reload' => true,
            ], 409);
        }

        try {
            $service->updateTermin($termin, [
                'titel'        => $termin->titel,
                'beschreibung' => $termin->beschreibung,
                'ort'          => $termin->ort,
                'beginn'       => $validated['beginn'],
                'ende'         => $validated['ende'],
                'ganztaegig'   => $request->boolean('ganztaegig', $termin->ganztaegig),
                'rrule'        => $termin->rrule,
            ]);

            return response()->json([
                'success'    => true,
                'message'    => 'Termin wurde verschoben.',
                'updated_at' => $termin->fresh()->updated_at->toIso8601String(),
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Termin verschieben fehlgeschlagen', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Termin konnte nicht verschoben werden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Termin löschen.
     */
    public function destroy(OxTermin $termin)
    {
        $user    = auth()->user();
        $service = app(OxCalendarService::class);
        $termin->load('kalender');

        if (!$service->canEditTermin($user, $termin->kalender)) {
            abort(403, 'Keine Schreibberechtigung für diesen Kalender.');
        }

        $titel = $termin->titel;

        try {
            $service->deleteTermin($termin);
            return redirectBack('success', 'Termin "' . $titel . '" wurde gelöscht.');
        } catch (\RuntimeException $e) {
            Log::warning('Termin löschen fehlgeschlagen', ['error' => $e->getMessage()]);
            return redirectBack('danger', 'Termin konnte nicht gelöscht werden: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Hilfsmethoden
    // =========================================================================

    /**
     * Lädt einen externen iCal-Feed (gecacht, 5 min), parst ihn mit sabre/vobject
     * und gibt alle Termine im angegebenen Zeitfenster als FullCalendar-Event-Array zurück.
     */
    protected function fetchIcalFeedEvents(UserIcalFeed $feed, string $start, string $end): Collection
    {
        // Cache-Key enthält Feed-ID + URL-Hash → bei URL-Änderung automatisch invalidiert
        $cacheKey = 'ical_feed_raw_' . $feed->id . '_' . substr(md5($feed->url), 0, 8);

        $icalData = Cache::remember($cacheKey, 300, function () use ($feed) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['Accept' => 'text/calendar, application/octet-stream, */*'])
                    ->get($feed->url);

                if ($response->successful()) {
                    $feed->updateQuietly(['letzter_abruf' => now(), 'fehler_meldung' => null]);
                    return $response->body();
                }

                $feed->updateQuietly(['fehler_meldung' => 'HTTP ' . $response->status()]);
                return null;
            } catch (\Exception $e) {
                $feed->updateQuietly(['fehler_meldung' => Str::limit($e->getMessage(), 200)]);
                return null;
            }
        });

        if (!$icalData) {
            return collect();
        }

        try {
            $vcalendar = Reader::read($icalData);

            $startDt = new \DateTimeImmutable($start);
            $endDt   = new \DateTimeImmutable($end);
            $expanded = $vcalendar->expand($startDt, $endDt);

            $events = collect();

            foreach ($expanded->VEVENT ?? [] as $vevent) {
                try {
                    $beginn = Carbon::parse($vevent->DTSTART->getDateTime()->format('c'));
                    $ende   = isset($vevent->DTEND)
                        ? Carbon::parse($vevent->DTEND->getDateTime()->format('c'))
                        : $beginn->copy()->addHour();

                    // Ganztags-Erkennung: DATE-Typ hat kein 'T' im String-Wert
                    $rawDtstart = (string) $vevent->DTSTART;
                    $allDay     = !str_contains($rawDtstart, 'T');

                    $uid = isset($vevent->UID) ? (string) $vevent->UID : uniqid('', true);

                    $events->push([
                        'id'     => 'ical_' . $feed->id . '_' . substr(md5($uid), 0, 12),
                        'title'  => isset($vevent->SUMMARY) ? (string) $vevent->SUMMARY : 'Termin',
                        'start'  => $beginn->toIso8601String(),
                        'end'    => $ende->toIso8601String(),
                        'allDay' => $allDay,
                        'color'  => $feed->farbe ?? '#6366f1',
                        'extendedProps' => [
                            'calendarId'   => 'ical_' . $feed->id,
                            'calendarName' => $feed->name,
                            'ort'          => isset($vevent->LOCATION) ? (string) $vevent->LOCATION : '',
                            'beschreibung' => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : '',
                            'isIcalFeed'   => true,
                        ],
                    ]);
                } catch (\Exception $e) {
                    // Einzelnes defektes Vorkommen überspringen
                }
            }

            return $events;
        } catch (\Exception $e) {
            Log::warning('iCal-Feed-Parsing fehlgeschlagen für Feed #' . $feed->id, [
                'error' => $e->getMessage(),
                'url'   => $feed->url,
            ]);
            return collect();
        }
    }

    /**
     * Expandiert einen RRULE-Termin mit sabre/vobject und gibt alle Vorkommen
     * innerhalb des angegebenen Zeitfensters zurück.
     *
     * @return array  [['beginn' => Carbon, 'ende' => Carbon], ...]
     */
    protected function expandRruleInWoche(OxTermin $termin, Carbon $von, Carbon $bis): array
    {
        try {
            // VCALENDAR aufbauen – bevorzugt aus raw_ical, sonst aus DB-Feldern
            if (!empty($termin->raw_ical)) {
                $vcalendar = Reader::read($termin->raw_ical);
            } else {
                $vcalendar = new \Sabre\VObject\Component\VCalendar();
                $vevent    = $vcalendar->add('VEVENT', [
                    'UID'     => $termin->ox_uid ?? ('termin-' . $termin->id),
                    'SUMMARY' => $termin->titel,
                    'DTSTART' => $termin->beginn->toDateTime(),
                    'DTEND'   => $termin->ende->toDateTime(),
                ]);
                $vevent->add('RRULE', $termin->rrule);

                if (!empty($termin->exdates)) {
                    foreach ((array) $termin->exdates as $exdate) {
                        $vevent->add('EXDATE', $exdate);
                    }
                }
            }

            /** @var \Sabre\VObject\Component\VCalendar $expanded */
            $expanded = $vcalendar->expand(
                new \DateTimeImmutable($von->toIso8601String()),
                new \DateTimeImmutable($bis->copy()->addDay()->toIso8601String()) // +1 Tag wegen Exklusiv-Ende
            );
        } catch (\Exception $e) {
            Log::warning('RRULE-Expansion (PDF) fehlgeschlagen für Termin ' . $termin->id, [
                'error' => $e->getMessage(),
                'rrule' => $termin->rrule,
            ]);
            return [];
        }

        $occurrences = [];
        foreach ($expanded->VEVENT ?? [] as $vevent) {
            try {
                $occurrences[] = [
                    'beginn' => Carbon::parse($vevent->DTSTART->getDateTime()->format('c')),
                    'ende'   => Carbon::parse($vevent->DTEND->getDateTime()->format('c')),
                ];
            } catch (\Exception $e) {
                // Einzelnes defektes Vorkommen überspringen
            }
        }

        return $occurrences;
    }

    /**
     * Sichtbare Kalender für einen User ermitteln.
     * 1. Admin (manage calendar) → alle sichtbaren Kalender
     * 2. Kalender ohne Gruppen  → öffentlich (view calendar reicht)
     * 3. Kalender mit Gruppen   → User muss in mind. einer Gruppe sein
     */
    protected function sichtbareKalender(User $user): Collection
    {
        return OxCalendar::where('sichtbar', true)
            ->with('groups')
            ->get()
            ->filter(function (OxCalendar $calendar) use ($user) {
                if ($user->can('manage calendar')) {
                    return true;
                }

                if ($calendar->groups->isEmpty()) {
                    return $user->can('view calendar');
                }

                $calendarGroupIds = $calendar->groups->pluck('id');
                $userGroupIds     = $user->groups()->pluck('id');
                return $calendarGroupIds->intersect($userGroupIds)->isNotEmpty();
            });
    }

    /**
     * Prüft ob ein User in einen bestimmten Kalender schreiben darf.
     */
    protected function canWriteCalendar(User $user, OxCalendar $calendar): bool
    {
        if (!$user->can('create calendar events') || !$calendar->schreibbar) {
            return false;
        }

        if ($user->can('manage calendar')) {
            return true;
        }

        if ($calendar->groups->isEmpty()) {
            return true;
        }

        $userGroupIds = $user->groups()->pluck('id');
        return $calendar->groups()
            ->wherePivot('schreibbar', true)
            ->whereIn('groups.id', $userGroupIds)
            ->exists();
    }
}

