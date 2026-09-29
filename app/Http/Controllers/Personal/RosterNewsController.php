<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateRosterNewsRequest;
use App\Models\personal\Roster;
use App\Models\personal\RosterNews;

class RosterNewsController extends Controller
{
    public function store(CreateRosterNewsRequest $request, Roster $roster)
    {
        $this->authorize('manage', $roster);
        $roster->news()->create($request->validated());

        return redirectBack('success', 'Hinweis gespeichert.');
    }

    public function destroy(RosterNews $news)
    {
        $this->authorize('manage', $news->roster);
        $news->delete();

        return redirectBack('success', 'Hinweis gelöscht.');
    }
}
