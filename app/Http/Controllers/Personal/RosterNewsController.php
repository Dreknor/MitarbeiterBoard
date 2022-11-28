<?php

namespace App\Http\Controllers\Personal;


use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateRosterNewsRequest;
use App\Models\personal\Roster;
use App\Models\personal\RosterNews;

class RosterNewsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:create roster');
    }

    public function store(CreateRosterNewsRequest $request, Roster $roster)
    {
        $roster->news()->create($request->validated());

        return redirectBack('success', 'News gespeichert.');
    }

    public function destroy(RosterNews $news)
    {
        $news->delete();
        return redirectBack('success', 'Löschen erfolgreich');
    }
}
