<?php

namespace App\Http\Controllers;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use App\Models\UserIcalFeed;
use App\Services\Calendar\KalenderRaumService;
use App\Services\Calendar\TerminVerbundService;
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
use Illuminate\Validation\ValidationException;
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

        $service = app(OxCalendarService::class);
        $raum    = app(KalenderRaumService::class);

        // Schreibbare Kalender für den aktuellen User
        $schreibbareKalender = $kalender->filter(fn ($cal) => $service->canWriteCalendar($user, $cal))->values();
        $canCreate = $user->can('create calendar events') && $schreibbareKalender->isNotEmpty();

        // Persönliche iCal-Feeds des Users
        $icalFeeds = $user->icalFeeds()->where('aktiv', true)->get();

        $feedToken = \App\Models\Setting::where('module', 'Kalender')
            ->where('setting', 'feed_token_' . $user->id)
            ->value('value');

        return view('calendar.index', [
            'kalender'            => $kalender,
            'icalFeeds'           => $icalFeeds,
            'feedToken'           => $feedToken,
            'schreibbareKalender' => $schreibbareKalender,
            'defaultView'         => $defaultView,
            'canCreate'           => $canCreate,
            'canEdit'             => $kalender->contains(fn ($cal) => $service->canEditTermin($user, $cal)),
            'canImport'           => $canCreate && $user->canAny(['import calendar events', 'manage calendar']),
            'raeume'              => $raum->darfRaeumeSehen($user) ? $raum->raumListe() : collect(),
            'canBookRooms'        => $raum->darfRaeumeBuchen($user),
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
        $oxCacheKey = $service->eventsCacheKey(md5($start . $end . $user->id . ($calendarsParam === '' ? '*' : $oxIds->sort()->join(','))));

        $oxEvents = Cache::remember($oxCacheKey, 300, function () use ($user, $start, $end, $oxIds, $calendarsParam) {
            $sichtbareIds = $this->sichtbareKalender($user)->pluck('id');

            // Parameter gesetzt (auch 'none') → strikt filtern; fehlt er → alle sichtbaren
            $filterIds = $calendarsParam !== ''
                ? $oxIds->intersect($sichtbareIds)
                : $sichtbareIds;

            // Mehrtägige Termine, die vor dem Fenster beginnen, aber hineinragen, einschließen
            $termine = OxTermin::whereIn('ox_calendar_id', $filterIds)
                ->where(function ($query) use ($start, $end) {
                    $query->where(function ($q) use ($start, $end) {
                        $q->where('beginn', '<', $end)->where('ende', '>', $start);
                    })->orWhereNotNull('rrule');
                })
                ->with('kalender')
                ->orderBy('ox_calendar_id')
                ->orderBy('id')
                ->get();

            // Kopien eines Terminverbunds nur einmal anzeigen (Farbe des ersten sichtbaren Kalenders)
            $gruppen = $termine->groupBy(fn (OxTermin $t) => $t->verbund_uid ?: 'id_' . $t->id);

            return $gruppen->map(function (Collection $kopien) {
                /** @var OxTermin $termin */
                $termin = $kopien->first();

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
                        'kalender'     => $kopien->map(fn (OxTermin $k) => [
                            'id'    => $k->ox_calendar_id,
                            'name'  => $k->kalender->name ?? '',
                            'farbe' => $k->kalender->farbe ?? '#3b82f6',
                        ])->values(),
                        'ort'          => $termin->ort,
                        'beschreibung' => $termin->beschreibung,
                        'status'       => $termin->status,
                        'updatedAt'    => $termin->updated_at?->toIso8601String(),
                    ],
                ];

                if ($termin->rrule) {
                    // Ganztägige Termine benötigen DTSTART;VALUE=DATE im iCal-Format,
                    // da sonst FullCalendar-rrule-Plugin sie nicht korrekt rendert.
                    if ($termin->ganztaegig) {
                        $dtstart = 'DTSTART;VALUE=DATE:' . $termin->beginn->format('Ymd');
                    } else {
                        $dtstart = 'DTSTART:' . $termin->beginn->utc()->format('Ymd\THis\Z');
                    }
                    $event['rrule'] = $dtstart . "\nRRULE:" . $termin->rrule;
                    if ($termin->exdates) {
                        $event['exdate'] = $termin->exdates;
                    }
                    // Dauer berechnen: bei ganztägigen Terminen anhand der Tage-Differenz,
                    // da die Zeitdifferenz 0:00 ergeben würde (Mitternacht bis Mitternacht).
                    $diff = $termin->beginn->diff($termin->ende);
                    if ($termin->ganztaegig) {
                        $totalDays = (int) $termin->beginn->diffInDays($termin->ende);
                        $event['duration'] = ['days' => max(1, $totalDays)];
                    } else {
                        $totalMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                        $hours   = intdiv($totalMinutes, 60);
                        $minutes = $totalMinutes % 60;
                        $event['duration'] = sprintf('%02d:%02d', $hours, $minutes);
                    }
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

        // ── Raumbelegung (Raumplanung) ───────────────────────────────────────────
        $raumEvents = collect();
        $roomIds    = collect(explode(',', (string) $request->query('rooms', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->take(20)
            ->values();

        $raumService = app(KalenderRaumService::class);
        if ($roomIds->isNotEmpty() && $raumService->darfRaeumeSehen($user)) {
            $raumEvents = $raumService->belegungEvents(
                $roomIds->all(),
                Carbon::parse($start),
                Carbon::parse($end)
            );
        }

        return response()->json($oxEvents->concat($icalEvents)->concat($raumEvents)->values());
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

        $service  = app(OxCalendarService::class);
        $verbund  = $termin->verbund()->filter(fn (OxTermin $k) => $sichtbareIds->contains($k->ox_calendar_id));
        $buchung  = $termin->raumBuchung();

        // Ganztägige Termine: DTEND ist exklusiv – im Formular wird das letzte Tag angezeigt.
        $endeFormular = $termin->ganztaegig
            ? $termin->ende->copy()->subDay()->max($termin->beginn)->format('Y-m-d')
            : $termin->ende->format('Y-m-d\TH:i');

        return response()->json([
            'id'          => $termin->id,
            'titel'       => $termin->titel,
            'beschreibung' => $termin->beschreibung,
            'ort'         => $termin->ort,
            'beginn'      => $termin->beginn->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'ende'        => $termin->ganztaegig
                ? $termin->ende->copy()->subDay()->max($termin->beginn)->format('d.m.Y')
                : $termin->ende->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'beginn_iso'  => $termin->beginn->toIso8601String(),
            'ende_iso'    => $termin->ende->toIso8601String(),
            'beginn_formular' => $termin->ganztaegig
                ? $termin->beginn->format('Y-m-d')
                : $termin->beginn->format('Y-m-d\TH:i'),
            'ende_formular'   => $endeFormular,
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
            'verbund'     => $verbund->map(fn (OxTermin $k) => [
                'termin_id' => $k->id,
                'id'        => $k->ox_calendar_id,
                'name'      => $k->kalender->name ?? '',
                'farbe'     => $k->kalender->farbe ?? '#3b82f6',
                'can_edit'  => $service->canEditTermin($user, $k->kalender),
            ])->values(),
            'raum'        => $buchung ? [
                'id'   => $buchung->room_id,
                'name' => $buchung->room?->name,
                'zeit' => \Carbon\Carbon::parse($buchung->start)->format('H:i') . '–' . \Carbon\Carbon::parse($buchung->end)->format('H:i'),
            ] : null,
            'can_edit'    => $service->canEditTermin($user, $termin->kalender),
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
            ->get()
            ->unique(fn (OxTermin $t) => $t->verbund_uid ?: 'id_' . $t->id);

        // ── 2. Wiederkehrende Termine (RRULE) – Vorkommen im Zeitfenster ─────
        //    Wir laden alle RRULE-Termine dieser Kalender und expandieren sie
        //    mit sabre/vobject auf das Wochenfenster.
        $rruleTermine = OxTermin::whereIn('ox_calendar_id', $calendarIds)
            ->whereNotNull('rrule')
            ->with('kalender')
            ->get()
            ->unique(fn (OxTermin $t) => $t->verbund_uid ?: 'id_' . $t->id);

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
            ->get()
            ->unique(fn (OxTermin $t) => $t->verbund_uid ?: 'id_' . $t->id);

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
            ->limit(40)
            ->get()
            ->unique(fn (OxTermin $t) => $t->verbund_uid ?: 'id_' . $t->id)
            ->take(20)
            ->values();

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
     * Neuen Termin anlegen – in einem oder mehreren Kalendern, optional mit Raumbuchung.
     */
    public function store(Request $request)
    {
        $user    = auth()->user();
        $service = app(OxCalendarService::class);

        $validated = $request->validate(array_merge($this->terminRegeln(), [
            'kalender_ids'   => 'required_without:ox_calendar_id|array|min:1|max:20',
            'kalender_ids.*' => 'integer|distinct|exists:ox_calendars,id',
            'ox_calendar_id' => 'required_without:kalender_ids|integer|exists:ox_calendars,id',
        ]), $this->terminMeldungen());

        $kalender = OxCalendar::whereIn('id', $this->kalenderIdsAus($validated))->with('groups')->get();

        foreach ($kalender as $kal) {
            if (!$service->canWriteCalendar($user, $kal)) {
                abort(403, 'Keine Schreibberechtigung für den Kalender "' . $kal->name . '".');
            }
        }

        $daten = $this->terminDaten($validated, $request);

        $ergebnis = app(TerminVerbundService::class)
            ->anlegen($user, $kalender, $daten, $validated['room_id'] ?? null);

        return $this->ergebnisMeldung(
            $ergebnis,
            'Termin "' . $daten['titel'] . '" wurde angelegt',
            'Termin konnte nicht angelegt werden'
        );
    }

    /**
     * Termin aktualisieren (mit Optimistic Locking via expected_updated_at).
     * Änderungen gelten für alle Kopien des Terminverbunds.
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

        $validated = $request->validate(array_merge($this->terminRegeln(), [
            'kalender_ids'   => 'sometimes|array|min:1|max:20',
            'kalender_ids.*' => 'integer|distinct|exists:ox_calendars,id',
        ]), $this->terminMeldungen());

        $daten = $this->terminDaten($validated, $request);
        if ($request->has('raum_aendern')) {
            $daten['room_id'] = $validated['room_id'] ?? null;
        }

        $ergebnis = app(TerminVerbundService::class)->aktualisieren(
            $user,
            $termin,
            $daten,
            $validated['kalender_ids'] ?? null
        );

        return $this->ergebnisMeldung(
            $ergebnis,
            'Termin "' . $daten['titel'] . '" wurde aktualisiert',
            'Termin konnte nicht aktualisiert werden'
        );
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
            $ergebnis = app(TerminVerbundService::class)->verschieben(
                $user,
                $termin,
                Carbon::parse($validated['beginn'])->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                Carbon::parse($validated['ende'])->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                $request->boolean('ganztaegig', $termin->ganztaegig)
            );
        } catch (ValidationException $e) {
            return response()->json([
                'error' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        if ($ergebnis['termine']->isEmpty()) {
            Log::warning('Termin verschieben fehlgeschlagen', ['fehler' => $ergebnis['fehler']]);
            return response()->json([
                'error' => 'Termin konnte nicht verschoben werden: ' . TerminVerbundService::fehlerText($ergebnis['fehler']),
            ], 500);
        }

        return response()->json([
            'success'    => true,
            'message'    => empty($ergebnis['fehler'])
                ? 'Termin wurde verschoben.'
                : 'Termin wurde verschoben, aber nicht überall: ' . TerminVerbundService::fehlerText($ergebnis['fehler']),
            'updated_at' => $termin->fresh()->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Termin löschen. Standard: alle Kopien des Verbunds; mit nur_dieser=1 nur diese Kopie.
     */
    public function destroy(Request $request, OxTermin $termin)
    {
        $user    = auth()->user();
        $service = app(OxCalendarService::class);
        $termin->load('kalender');

        if (!$service->canEditTermin($user, $termin->kalender)) {
            abort(403, 'Keine Schreibberechtigung für diesen Kalender.');
        }

        $titel    = $termin->titel;
        $ergebnis = app(TerminVerbundService::class)->loeschen($user, $termin, !$request->boolean('nur_dieser'));

        if ($ergebnis['geloescht'] === 0) {
            Log::warning('Termin löschen fehlgeschlagen', ['fehler' => $ergebnis['fehler']]);
            return redirectBack('danger', 'Termin konnte nicht gelöscht werden: ' . TerminVerbundService::fehlerText($ergebnis['fehler']));
        }

        if (!empty($ergebnis['fehler'])) {
            return redirectBack('warning', 'Termin "' . $titel . '" wurde teilweise gelöscht. Fehler: ' . TerminVerbundService::fehlerText($ergebnis['fehler']));
        }

        return redirectBack('success', 'Termin "' . $titel . '" wurde gelöscht.');
    }

    /**
     * Verfügbarkeit der buchbaren Räume für das Terminformular (JSON).
     */
    public function raumVerfuegbarkeit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'beginn'    => 'required|date',
            'ende'      => 'required|date|after:beginn',
            'termin_id' => 'nullable|integer|exists:ox_termine,id',
        ]);

        $verbundUid = isset($validated['termin_id'])
            ? OxTermin::whereKey($validated['termin_id'])->value('verbund_uid')
            : null;

        return response()->json(app(KalenderRaumService::class)->verfuegbarkeit(
            Carbon::parse($validated['beginn']),
            Carbon::parse($validated['ende']),
            $verbundUid
        ));
    }

    /**
     * Gemeinsame Validierungsregeln für Termin anlegen/bearbeiten.
     */
    protected function terminRegeln(): array
    {
        return [
            'titel'        => 'required|string|max:255',
            'beginn'       => 'required|date',
            'ende'         => 'required|date|after_or_equal:beginn',
            'ort'          => 'nullable|string|max:255',
            'beschreibung' => 'nullable|string|max:5000',
            'ganztaegig'   => 'boolean',
            'rrule'        => 'nullable|string|max:500',
            'room_id'      => 'nullable|integer|exists:rooms,id',
        ];
    }

    protected function terminMeldungen(): array
    {
        return [
            'kalender_ids.required_without' => 'Bitte mindestens einen Kalender auswählen.',
            'kalender_ids.min'              => 'Bitte mindestens einen Kalender auswählen.',
            'ende.after_or_equal'           => 'Das Ende darf nicht vor dem Beginn liegen.',
        ];
    }

    /**
     * @return int[]
     */
    protected function kalenderIdsAus(array $validated): array
    {
        return collect($validated['kalender_ids'] ?? [$validated['ox_calendar_id']])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Formulardaten in Service-Daten umwandeln.
     * Ganztägig: Das Formular liefert das letzte Datum inklusiv, iCal erwartet DTEND exklusiv.
     */
    protected function terminDaten(array $validated, Request $request): array
    {
        $ganztaegig = $request->boolean('ganztaegig');
        $beginn     = Carbon::parse($validated['beginn']);
        $ende       = Carbon::parse($validated['ende']);

        if ($ganztaegig) {
            $beginn = $beginn->startOfDay();
            $ende   = $ende->startOfDay()->addDay();
            if ($ende->lte($beginn)) {
                $ende = $beginn->copy()->addDay();
            }
        }

        return [
            'titel'        => $validated['titel'],
            'beschreibung' => $validated['beschreibung'] ?? null,
            'ort'          => $validated['ort'] ?? null,
            'beginn'       => $beginn->format('Y-m-d H:i:s'),
            'ende'         => $ende->format('Y-m-d H:i:s'),
            'ganztaegig'   => $ganztaegig,
            'rrule'        => $validated['rrule'] ?? null,
        ];
    }

    /**
     * Flash-Meldung aus einem Verbund-Ergebnis (inkl. Teilfehlern pro Kalender).
     */
    protected function ergebnisMeldung(array $ergebnis, string $erfolg, string $misserfolg)
    {
        if ($ergebnis['termine']->isEmpty()) {
            Log::warning($misserfolg, ['fehler' => $ergebnis['fehler']]);
            return redirectBack('danger', $misserfolg . ': ' . TerminVerbundService::fehlerText($ergebnis['fehler']));
        }

        $kalenderNamen = $ergebnis['termine']->map(fn (OxTermin $t) => $t->kalender?->name)->filter()->unique();
        $text = $erfolg;
        if ($kalenderNamen->count() > 1) {
            $text .= ' (' . $kalenderNamen->implode(', ') . ')';
        }
        if (!empty($ergebnis['buchung'])) {
            $text .= ' – Raum ' . ($ergebnis['buchung']->room?->name ?? '') . ' gebucht';
        }

        if (!empty($ergebnis['fehler'])) {
            return redirectBack('warning', $text . '. Nicht übernommen: ' . TerminVerbundService::fehlerText($ergebnis['fehler']));
        }

        return redirectBack('success', $text . '.');
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
     * Sichtbare Kalender für einen User (Regelwerk siehe OxCalendarService::sichtbareKalender).
     */
    protected function sichtbareKalender(User $user): Collection
    {
        return app(OxCalendarService::class)->sichtbareKalender($user);
    }
}

