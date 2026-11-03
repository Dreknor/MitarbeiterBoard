<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateTaskRequest;
use App\Http\Requests\personal\EditRosterEventRequest;
use App\Http\Requests\personal\TrashRosterDayRequest;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $this->rosters->pruefeTag($roster, $request->date);

        $ergebnis = $this->rosters->termineAnlegen($roster, $request->safe()->only(['event', 'date', 'start', 'end']), (array) $request->input('employes', []));
        $uebersprungen = $ergebnis['uebersprungen']->map(fn ($u) => $u->vorname ?? $u->name)->all();

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
        $this->rosters->pruefeTag($roster, $request->date);

        $mitarbeitende = $this->rosters->mitarbeitende($roster)->keyBy('id');
        $personen = collect($request->input('employes', []))->map(fn ($id) => (int) $id)->filter(fn ($id) => $mitarbeitende->has($id))->values();
        $daten = $request->safe()->only(['event', 'date', 'start', 'end']);
        $uebersprungen = [];

        $erste = $personen->shift();
        if ($erste !== null && $this->rosters->kollision($roster, $erste, $request->date, $request->start, $request->end, $rosterEvent->id)) {
            return $this->antwort($request, $roster, $request->date, ['warning', 'Überschneidung mit einem anderen Termin von '.($mitarbeitende[$erste]->vorname ?? '').' – nicht gespeichert.']);
        }
        $rosterEvent->update($daten + ['employe_id' => $erste]);

        foreach ($personen as $id) {
            if ($this->rosters->kollision($roster, $id, $request->date, $request->start, $request->end)) {
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
        $this->rosters->pruefeTag($roster, $data['date']);

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

        $konflikt = $data['employe_id'] !== null && $this->rosters->kollision($roster, (int) $data['employe_id'], $data['date'], $start, $ende, $task->id);

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
        $this->rosters->pruefeTag($roster, $request->date);

        $roster->events()->whereDate('date', $request->date)->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', RosterEvents::SOURCE_ABWESENHEIT))->get()->each->delete();
        $roster->working_times()->whereDate('date', $request->date)->get()->each->delete();

        return $this->antwort($request, $roster, $request->date, ['success', 'Alle Dienste und Termine des Tages wurden entfernt.']);
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
        $kalender  = $this->rosters->importKalender(auth()->user());
        $startDate = $roster->start_date->copy()->startOfDay();
        $endDate   = $startDate->copy()->addDays(6)->endOfDay();

        $selectedKalenderId = $request->filled('kalender_id')
            ? (int) $request->kalender_id
            : $kalender->first()?->id;

        $termine = $selectedKalenderId && $kalender->pluck('id')->contains($selectedKalenderId)
            ? $this->rosters->kalenderImportVorschau($roster, $selectedKalenderId)
            : collect();

        return view('personal.rosters.import_calendar', compact(
            'roster', 'kalender', 'termine', 'selectedKalenderId', 'startDate', 'endDate'
        ));
    }

    /**
     * Import: Legt ausgewählte Kalender-Termine als nichtzugewiesene Dienstplan-Ereignisse an.
     */
    public function importFromCalendar(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);
        $request->validate([
            'ox_termin_ids'   => 'required|array|min:1',
            'ox_termin_ids.*' => 'required|string',
        ]);

        $ergebnis = $this->rosters->kalenderImportieren($roster, auth()->user(), $request->ox_termin_ids);

        $msg = $ergebnis['angelegt']->count() . ' Termin(e) importiert';
        if ($ergebnis['uebersprungen'] !== []) {
            $msg .= ', ' . count($ergebnis['uebersprungen']) . ' bereits vorhanden übersprungen';
        }

        return redirect()->route('roster.show', $roster->id)
            ->with('Meldung', $msg)
            ->with('type', 'success');
    }
}
