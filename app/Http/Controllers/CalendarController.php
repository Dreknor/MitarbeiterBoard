<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOxTerminRequest;
use App\Http\Requests\UpdateOxTerminRequest;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use App\Services\OxCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;

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

        return view('calendar.index', [
            'kalender'            => $kalender,
            'schreibbareKalender' => $schreibbareKalender,
            'defaultView'         => $defaultView,
            'canCreate'           => $user->can('create calendar events') && $schreibbareKalender->isNotEmpty(),
            'feedToken'           => $user->calendar_token,
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
        $service = app(OxCalendarService::class);
        $cacheKey = $service->eventsCacheKey(md5($start . $end . $user->id . ($calendarsParam ?? 'all')));

        $events = Cache::remember($cacheKey, 300, function () use ($user, $start, $end, $calendarsParam) {
            // Sichtbare Kalender-IDs ermitteln
            $sichtbareIds = $this->sichtbareKalender($user)->pluck('id');

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

            return $termine->map(function (OxTermin $termin) {
                $event = [
                    'id'    => $termin->id,
                    'title' => $termin->titel,
                    'start' => $termin->beginn->toIso8601String(),
                    'end'   => $termin->ende->toIso8601String(),
                    'allDay' => $termin->ganztaegig,
                    'color' => $termin->kalender->farbe ?? '#3b82f6',
                    'extendedProps' => [
                        'terminId'     => $termin->id,
                        'calendarId'   => $termin->ox_calendar_id,
                        'calendarName' => $termin->kalender->name ?? '',
                        'ort'          => $termin->ort,
                        'beschreibung' => $termin->beschreibung,
                        'status'       => $termin->status,
                    ],
                ];

                // RRULE für FullCalendar-Plugin
                if ($termin->rrule) {
                    $event['rrule'] = 'DTSTART:' . $termin->beginn->format('Ymd\THis\Z') . "\nRRULE:" . $termin->rrule;

                    if ($termin->exdates) {
                        $event['exdate'] = $termin->exdates;
                    }

                    // duration statt end bei rrule-Events
                    $duration = $termin->beginn->diff($termin->ende);
                    $event['duration'] = sprintf('%02d:%02d', $duration->h, $duration->i);
                    unset($event['end']);
                }

                return $event;
            })->values();
        });

        return response()->json($events);
    }

    /**
     * Termin-Detail (JSON für Modal).
     */
    public function show(OxTermin $termin): JsonResponse
    {
        $user = auth()->user();

        // Sichtbarkeitsprüfung
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
                && $this->canWriteCalendar($user, $termin->kalender),
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

        $sichtbareIds = $this->sichtbareKalender($user)->pluck('id');

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
    // Hilfsmethoden
    // ========================================================================

    /**
     * Sichtbare Kalender für einen User ermitteln.
     * Regelwerk:
     * 1. Admin (manage calendar) → alle sichtbaren Kalender
     * 2. Kalender ohne Gruppen → öffentlich (view calendar reicht)
     * 3. Kalender mit Gruppen → User muss in mindestens einer Gruppe sein
     */
    protected function sichtbareKalender(User $user)
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
                $userGroupIds = $user->groups_rel()->pluck('groups.id');
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

        // Kalender ohne Gruppen → öffentlich schreibbar
        if ($calendar->groups->isEmpty()) {
            return true;
        }

        // User muss in mindestens einer Gruppe sein, die für diesen Kalender schreibbar ist
        $userGroupIds = $user->groups_rel()->pluck('groups.id');

        return $calendar->groups()
            ->whereIn('groups.id', $userGroupIds)
            ->wherePivot('schreibbar', true)
            ->exists();
    }

    // ========================================================================
    // Schreibende Methoden (TODO 16)
    // ========================================================================

    /**
     * Neuen Termin erstellen (→ OX).
     */
    public function store(StoreOxTerminRequest $request, OxCalendarService $service)
    {
        $calendar = OxCalendar::findOrFail($request->validated()['ox_calendar_id']);
        $user     = auth()->user();

        // Gruppen-basierte Schreibberechtigung prüfen
        if (!$this->canWriteCalendar($user, $calendar)) {
            abort(403, 'Keine Schreibberechtigung für diesen Kalender.');
        }

        try {
            $termin = $service->createTermin($calendar, $request->validated());

            return redirectBack('success', "Termin \"{$termin->titel}\" wurde erstellt.");
        } catch (\RuntimeException $e) {
            return redirectBack('danger', 'Fehler beim Erstellen des Termins: ' . $e->getMessage());
        }
    }

    /**
     * Termin bearbeiten (→ OX).
     * Mit Optimistic Locking über expected_updated_at.
     */
    public function update(UpdateOxTerminRequest $request, OxTermin $termin, OxCalendarService $service)
    {
        $user = auth()->user();

        // Gruppen-basierte Schreibberechtigung prüfen
        if (!$this->canWriteCalendar($user, $termin->kalender)) {
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
            $service->updateTermin($termin, $request->validated());

            return redirectBack('success', "Termin \"{$termin->titel}\" wurde aktualisiert.");
        } catch (\RuntimeException $e) {
            return redirectBack('danger', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    /**
     * Termin löschen (→ OX).
     */
    public function destroy(OxTermin $termin, OxCalendarService $service)
    {
        $user = auth()->user();

        if (!$user->can('edit calendar events') || !$this->canWriteCalendar($user, $termin->kalender)) {
            abort(403, 'Keine Berechtigung zum Löschen.');
        }

        try {
            $titel = $termin->titel;
            $service->deleteTermin($termin);

            return redirectBack('success', "Termin \"{$titel}\" wurde gelöscht.");
        } catch (\RuntimeException $e) {
            return redirectBack('danger', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }
}

