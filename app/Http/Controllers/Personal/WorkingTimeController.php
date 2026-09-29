<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateWorkingTimeRequest;
use App\Models\personal\Roster;
use App\Models\personal\WorkingTime;
use App\Services\Personal\Zeit\RosterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\GoogleCalendar\Event;

class WorkingTimeController extends Controller
{
    public function __construct(private readonly RosterService $rosters)
    {
    }

    /**
     * Arbeitszeit einer Person an einem Tag setzen (leere Zeiten = frei).
     */
    public function store(CreateWorkingTimeRequest $request)
    {
        $roster = Roster::findOrFail($request->roster_id);
        $this->authorize('manage', $roster);

        $tag = Carbon::parse($request->date)->startOfDay();
        abort_if($tag->lt($roster->start_date->copy()->startOfDay()) || $tag->gt($roster->weekEnd()), 422, 'Das Datum liegt außerhalb der Dienstplanwoche.');
        abort_unless($this->rosters->mitarbeitende($roster)->contains('id', (int) $request->employe_id), 422, 'Die Person gehört nicht zu dieser Abteilung.');

        $workingTime = WorkingTime::where('roster_id', $roster->id)
            ->where('employe_id', $request->employe_id)
            ->whereDate('date', $tag->toDateString())
            ->first() ?? new WorkingTime(['roster_id' => $roster->id, 'employe_id' => $request->employe_id, 'date' => $tag->toDateString()]);

        $workingTime->fill([
            'start' => $request->start,
            'end' => $request->end,
            'function' => $request->function,
        ])->save();

        $hinweis = $this->kalenderSync($workingTime, $roster);

        if ($request->expectsJson()) {
            return response()->json(['type' => $hinweis ? 'warning' : 'success', 'message' => $hinweis ?? 'Arbeitszeit gespeichert.']);
        }

        return redirect(route('roster.show', $roster->id).'#tag-'.$tag->toDateString())
            ->with(['type' => $hinweis ? 'warning' : 'success', 'Meldung' => $hinweis ?? 'Arbeitszeit gespeichert.']);
    }

    /**
     * Optionaler Abgleich mit einem hinterlegten Google-Kalender. Fehler werden protokolliert
     * und als Hinweis gemeldet statt verschluckt.
     */
    private function kalenderSync(WorkingTime $workingTime, Roster $roster): ?string
    {
        $kalender = $workingTime->employe?->employe_data?->google_calendar_link;
        if ($kalender === null || $roster->type === 'template') {
            return null;
        }

        try {
            $hatZeit = $workingTime->start !== null && $workingTime->end !== null;

            if ($workingTime->googleCalendarId !== null) {
                $event = Event::find($workingTime->googleCalendarId, $kalender);
                if ($hatZeit) {
                    $event->startDateTime = $workingTime->start;
                    $event->endDateTime = $workingTime->end;
                    $event->save();
                } else {
                    $event->delete();
                    $workingTime->update(['googleCalendarId' => null]);
                }
            } elseif ($hatZeit) {
                $event = Event::create(['name' => 'Dienst', 'startDateTime' => $workingTime->start, 'endDateTime' => $workingTime->end], $kalender);
                $workingTime->update(['googleCalendarId' => $event->id]);
            }
        } catch (\Throwable $e) {
            Log::error('Arbeitszeit: Kalendereintrag fehlgeschlagen', ['working_time' => $workingTime->id, 'message' => $e->getMessage()]);

            return 'Arbeitszeit gespeichert, aber der Kalendereintrag ist fehlgeschlagen.';
        }

        return null;
    }
}
