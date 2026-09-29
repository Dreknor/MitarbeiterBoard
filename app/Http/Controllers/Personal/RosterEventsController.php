<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateTaskRequest;
use App\Http\Requests\personal\EditRosterEventRequest;
use App\Http\Requests\personal\TrashRosterDayRequest;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RosterEventsController extends Controller
{
    public function __construct(private readonly \App\Services\Personal\Zeit\RosterService $rosters)
    {
    }


    /**
     * Termin für eine oder mehrere Personen anlegen. Ohne Person landet er in der Merkliste.
     * Personen mit Überschneidung werden übersprungen und gemeldet.
     */
    public function store(CreateTaskRequest $request, Roster $roster)
    {
        $this->authorize('manage', $roster);
        $this->pruefeTag($roster, $request->date);

        $mitarbeitende = $this->rosters->mitarbeitende($roster)->keyBy('id');
        $personen = collect($request->input('employes', []))->map(fn ($id) => (int) $id)->filter(fn ($id) => $mitarbeitende->has($id));
        $uebersprungen = [];

        if ($personen->isEmpty()) {
            RosterEvents::create($request->safe()->only(['event', 'date', 'start', 'end']) + ['roster_id' => $roster->id, 'employe_id' => null]);
        }

        foreach ($personen as $id) {
            if ($this->kollision($roster, $id, $request->date, $request->start, $request->end)) {
                $uebersprungen[] = $mitarbeitende[$id]->vorname ?? $mitarbeitende[$id]->name;
                continue;
            }
            RosterEvents::create($request->safe()->only(['event', 'date', 'start', 'end']) + ['roster_id' => $roster->id, 'employe_id' => $id]);
        }

        return $this->antwort($request, $roster, $request->date, $uebersprungen === []
            ? ['success', 'Termin gespeichert.']
            : ['warning', 'Termin gespeichert – übersprungen wegen Überschneidung: '.implode(', ', $uebersprungen).'.']);
    }

    /**
     * Termin ändern. Werden mehrere Personen gewählt, bekommt jede weitere eine eigene Kopie.
     */
    public function update(EditRosterEventRequest $request, RosterEvents $rosterEvent)
    {
        $roster = $rosterEvent->roster;
        $this->authorize('manage', $roster);
        $this->pruefeTag($roster, $request->date);

        $mitarbeitende = $this->rosters->mitarbeitende($roster)->keyBy('id');
        $personen = collect($request->input('employes', []))->map(fn ($id) => (int) $id)->filter(fn ($id) => $mitarbeitende->has($id))->values();
        $daten = $request->safe()->only(['event', 'date', 'start', 'end']);
        $uebersprungen = [];

        $erste = $personen->shift();
        if ($erste !== null && $this->kollision($roster, $erste, $request->date, $request->start, $request->end, $rosterEvent->id)) {
            return $this->antwort($request, $roster, $request->date, ['warning', 'Überschneidung mit einem anderen Termin von '.($mitarbeitende[$erste]->vorname ?? '').' – nicht gespeichert.']);
        }
        $rosterEvent->update($daten + ['employe_id' => $erste]);

        foreach ($personen as $id) {
            if ($this->kollision($roster, $id, $request->date, $request->start, $request->end)) {
                $uebersprungen[] = $mitarbeitende[$id]->vorname ?? $mitarbeitende[$id]->name;
                continue;
            }
            RosterEvents::create($daten + ['roster_id' => $roster->id, 'employe_id' => $id]);
        }

        return $this->antwort($request, $roster, $request->date, $uebersprungen === []
            ? ['success', 'Termin gespeichert.']
            : ['warning', 'Gespeichert – übersprungen wegen Überschneidung: '.implode(', ', $uebersprungen).'.']);
    }

    /**
     * Drag & Drop im Raster (JSON).
     */
    public function dropUpdate(Request $request)
    {
        $data = $request->validate([
            'task' => ['required'],
            'employe_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['nullable', 'date_format:H:i'],
        ]);

        $task = RosterEvents::find((int) Str::after((string) $data['task'], 'task_'));
        if ($task === null) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $roster = $task->roster;
        $this->authorize('manage', $roster);
        $this->pruefeTag($roster, $data['date']);

        if ($data['employe_id'] !== null && !$this->rosters->mitarbeitende($roster)->contains('id', $data['employe_id'])) {
            return response()->json(['error' => 'invalid_employe'], 422);
        }

        [$fensterStart, $fensterEnde] = $roster->department->rosterDayWindow();
        $start = max($data['start'], $fensterStart);
        $dauer = $task->duration;
        $ende = $data['end'] ?? Carbon::createFromFormat('H:i', $start)->addMinutes($dauer)->format('H:i');
        if ($ende <= $start) {
            $ende = Carbon::createFromFormat('H:i', $start)->addMinutes(15)->format('H:i');
        }

        $konflikt = $data['employe_id'] !== null && $this->kollision($roster, (int) $data['employe_id'], $data['date'], $start, $ende, $task->id);

        if (!$konflikt) {
            $task->update([
                'employe_id' => $data['employe_id'],
                'date' => $data['date'],
                'start' => $start.':00',
                'end' => $ende.':00',
            ]);
        }

        $frisch = $task->fresh();

        return response()->json([
            'id' => $frisch->id,
            'employe_id' => $frisch->employe_id,
            'date' => $frisch->date->format('Y-m-d'),
            'event' => $frisch->event,
            'start' => $frisch->start->format('H:i'),
            'end' => $frisch->end->format('H:i'),
            'conflict' => $konflikt,
        ]);
    }

    public function destroy(Request $request, RosterEvents $rosterEvent)
    {
        $roster = $rosterEvent->roster;
        $this->authorize('manage', $roster);
        $tag = $rosterEvent->date->format('Y-m-d');
        $rosterEvent->delete();

        return $this->antwort($request, $roster, $tag, ['warning', 'Termin wurde gelöscht.']);
    }

    public function trashDay(Roster $roster, TrashRosterDayRequest $request)
    {
        $this->authorize('manage', $roster);
        $this->pruefeTag($roster, $request->date);

        $roster->events()->whereDate('date', $request->date)->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', RosterEvents::SOURCE_ABWESENHEIT))->get()->each->delete();
        $roster->working_times()->whereDate('date', $request->date)->get()->each->delete();

        return $this->antwort($request, $roster, $request->date, ['success', 'Alle Dienste und Termine des Tages wurden entfernt.']);
    }

    protected function termineFuerRosterImport(int $kalenderId, Carbon $startDate, Carbon $endDate): Collection
    {
        $termine = OxTermin::where('ox_calendar_id', $kalenderId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($subQuery) use ($endDate) {
                    $subQuery->whereNotNull('rrule')
                        ->where('beginn', '<=', $endDate);
                })
                    ->orWhereBetween('beginn', [$startDate, $endDate])
                    ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->where('beginn', '<=', $endDate)
                            ->where('ende', '>=', $startDate);
                    });
            })
            ->orderBy('beginn')
            ->get();

        $preview = collect();
        $rruleService = app(\App\Services\OxCalendarService::class);

        foreach ($termine as $termin) {
            if ($termin->rrule) {
                foreach ($rruleService->expandRruleTermine($termin, $startDate->copy(), $endDate->copy()) as $occurrence) {
                    $preview->push($this->makeImportPreviewItem($termin, $occurrence['beginn'], $occurrence['ende']));
                }

                continue;
            }

            $preview->push($this->makeImportPreviewItem($termin, $termin->beginn->copy(), $termin->ende->copy()));
        }

        return $preview->sortBy('beginn')->values();
    }

    protected function makeImportPreviewItem(OxTermin $termin, Carbon $beginn, Carbon $ende): object
    {
        return (object) [
            'id' => $termin->id,
            'selection_key' => $this->buildImportSelectionKey((int) $termin->id, $beginn->copy(), $beginn->copy(), $ende->copy()),
            'titel' => $termin->titel,
            'ort' => $termin->ort,
            'status' => $termin->status,
            'ganztaegig' => (bool) $termin->ganztaegig,
            'beginn' => $beginn,
            'ende' => $ende,
            'rrule' => $termin->rrule,
            'is_recurring' => (bool) $termin->rrule,
        ];
    }

    protected function buildImportSelectionKey(int $terminId, ?Carbon $date = null, ?Carbon $start = null, ?Carbon $end = null): string
    {
        if (!$date) {
            return (string) $terminId;
        }

        return implode('|', [
            (string) $terminId,
            $date->toDateString(),
            $start ? $start->format('H:i:s') : '00:00:00',
            $end ? $end->format('H:i:s') : '00:00:00',
        ]);
    }

    protected function parseImportSelection(string $selection): array
    {
        $parts = explode('|', $selection);
        $terminId = (int) ($parts[0] ?? 0);
        if (count($parts) < 2 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($parts[1] ?? ''))) {
            return [$terminId, null, null, null];
        }

        return [
            $terminId,
            $parts[1],
            $parts[2] ?? null,
            $parts[3] ?? null,
        ];
    }

    /**
     * Termin in die Merkliste verschieben (Zuordnung aufheben).
     */
    public function remember(Request $request, RosterEvents $event)
    {
        $this->authorize('manage', $event->roster);
        $event->update(['employe_id' => null]);

        return $this->antwort($request, $event->roster, $event->date->format('Y-m-d'), ['success', 'Termin liegt jetzt in der Merkliste.']);
    }

    private function pruefeTag(Roster $roster, string $datum): void
    {
        $tag = Carbon::parse($datum)->startOfDay();
        abort_if($tag->lt($roster->start_date->copy()->startOfDay()) || $tag->gt($roster->weekEnd()), 422, 'Das Datum liegt außerhalb der Dienstplanwoche.');
    }

    private function kollision(Roster $roster, int $employeId, string $datum, string $start, string $ende, ?int $ausser = null): bool
    {
        return $roster->events()
            ->where('employe_id', $employeId)
            ->whereDate('date', $datum)
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', RosterEvents::SOURCE_ABWESENHEIT))
            ->when($ausser, fn ($q) => $q->where('id', '!=', $ausser))
            ->where('start', '<', substr($ende, 0, 5).':00')
            ->where('end', '>', substr($start, 0, 5).':00')
            ->exists();
    }

    private function antwort(Request $request, Roster $roster, string $datum, array $meldung)
    {
        if ($request->expectsJson()) {
            return response()->json(['type' => $meldung[0], 'message' => $meldung[1]]);
        }

        return redirect(route('roster.show', $roster->id).'#tag-'.$datum)->with(['type' => $meldung[0], 'Meldung' => $meldung[1]]);
    }

    /**
     * Vorschau: Zeigt Kalender-Termine der Roster-Woche zur Auswahl an.
     */
    public function importFromCalendarPreview(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);
        $user      = auth()->user();
        $kalender  = $this->sichtbareKalender($user);
        $startDate = $roster->start_date->copy()->startOfDay();
        $endDate   = $startDate->copy()->addDays(6)->endOfDay();

        $selectedKalenderId = $request->filled('kalender_id')
            ? (int) $request->kalender_id
            : $kalender->first()?->id;

        $termine = collect();
        if ($selectedKalenderId && $kalender->pluck('id')->contains($selectedKalenderId)) {
            $termine = $this->termineFuerRosterImport($selectedKalenderId, $startDate, $endDate);
        }

        // Bereits importierte Vorkommen für diesen Roster ermitteln.
        $bereitsImportiert = $roster->events()
            ->whereNotNull('ox_termin_id')
            ->get()
            ->map(fn ($event) => $this->buildImportSelectionKey(
                (int) $event->ox_termin_id,
                $event->date->copy(),
                $event->start,
                $event->end
            ))
            ->values()
            ->toArray();

        return view('personal.rosters.import_calendar', compact(
            'roster', 'kalender', 'termine', 'selectedKalenderId',
            'startDate', 'endDate', 'bereitsImportiert'
        ));
    }

    /**
     * Import: Legt ausgewählte Kalender-Termine als nichtzugewiesene Dienstplan-Ereignisse an.
     */
    public function importFromCalendar(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);
        [$fensterStart, $fensterEnde] = $roster->department->rosterDayWindow();
        $request->validate([
            'ox_termin_ids'   => 'required|array|min:1',
            'ox_termin_ids.*' => 'required|string',
        ]);

        $user      = auth()->user();
        $kalender  = $this->sichtbareKalender($user);

        $bereitsImportiert = $roster->events()
            ->whereNotNull('ox_termin_id')
            ->get()
            ->map(fn ($event) => $this->buildImportSelectionKey(
                (int) $event->ox_termin_id,
                $event->date->copy(),
                $event->start,
                $event->end
            ))
            ->values()
            ->toArray();

        $importiert = 0;
        $uebersprungen = 0;

        foreach ($request->ox_termin_ids as $selection) {
            [$terminId, $eventDate, $startTime, $endTime] = $this->parseImportSelection((string) $selection);

            if (!is_numeric($terminId) || $terminId <= 0) {
                continue;
            }

            $termin = OxTermin::find((int) $terminId);
            if (!$termin) {
                continue;
            }

            if (!$kalender->pluck('id')->contains($termin->ox_calendar_id)) {
                continue;
            }

            $eventDateCarbon = $eventDate ? Carbon::parse($eventDate) : $termin->beginn->copy();
            $selectionKey = $this->buildImportSelectionKey(
                (int) $terminId,
                $eventDateCarbon,
                $startTime ? Carbon::parse($eventDateCarbon->toDateString() . ' ' . $startTime) : $termin->beginn->copy(),
                $endTime ? Carbon::parse($eventDateCarbon->toDateString() . ' ' . $endTime) : $termin->ende->copy()
            );

            if (in_array($selectionKey, $bereitsImportiert, true)) {
                $uebersprungen++;
                continue;
            }

            if ($termin->ganztaegig) {
                $start = $fensterStart.':00';
                $end   = $fensterEnde.':00';
            } else {
                $start = $startTime ?: $termin->beginn->format('H:i:s');
                $end   = $endTime ?: $termin->ende->format('H:i:s');

                if ($start < $fensterStart.':00') { $start = $fensterStart.':00'; }
                if ($end > $fensterEnde.':00')    { $end   = $fensterEnde.':00'; }
                if ($end <= $start)      { $end   = (new \DateTime($start))->modify('+15 minutes')->format('H:i:s'); }
            }

            $event = new RosterEvents([
                'roster_id'    => $roster->id,
                'employe_id'   => null,
                'date'         => $eventDateCarbon->toDateString(),
                'start'        => $start,
                'end'          => $end,
                'event'        => $termin->titel,
                'ox_termin_id' => $termin->id,
            ]);
            $event->save();

            Cache::forget('roster_'.$roster->id.'_'.$eventDateCarbon->format('Ymd'));
            $importiert++;
        }

        $msg = $importiert . ' Termin(e) importiert';
        if ($uebersprungen > 0) {
            $msg .= ', ' . $uebersprungen . ' bereits vorhanden übersprungen';
        }

        return redirect()->route('roster.show', $roster->id)
            ->with('Meldung', $msg)
            ->with('type', 'success');
    }

    /**
     * Hilfsmethode: Für den aktuellen User sichtbare Kalender laden.
     * (Analog zu CalendarController::sichtbareKalender)
     */
    protected function sichtbareKalender($user): Collection
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
}
