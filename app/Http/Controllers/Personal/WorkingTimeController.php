<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateWorkingTimeRequest;
use App\Models\personal\Roster;
use App\Services\Personal\Zeit\RosterService;
use Carbon\Carbon;

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
        [, $hinweis] = $this->rosters->arbeitszeitSetzen($roster, (int) $request->employe_id, $tag->toDateString(), $request->start, $request->end, $request->function);

        if ($request->expectsJson()) {
            return response()->json(['type' => $hinweis ? 'warning' : 'success', 'message' => $hinweis ?? 'Arbeitszeit gespeichert.']);
        }

        return redirect(route('roster.show', $roster->id).'#tag-'.$tag->toDateString())
            ->with(['type' => $hinweis ? 'warning' : 'success', 'Meldung' => $hinweis ?? 'Arbeitszeit gespeichert.']);
    }
}
