<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePositionRequest;
use App\Models\Positions;
use App\Models\User;
use Illuminate\Http\Request;

class PositionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage procedures');
    }

    public function store(CreatePositionRequest $request)
    {
        $position = new Positions($request->validated());
        $position->save();

        return redirect()->back()->with([
            'type'=>'success',
            'Meldung'=>'Position wurde erstellt',
        ]);
    }

    public function addUser(Request $request, Positions $position)
    {
        $data = $request->validate([
            'person_id' => 'required|integer|exists:users,id',
        ]);

        $position->users()->syncWithoutDetaching([$data['person_id']]);

        return redirect()->back()->with([
            'type'=>'success',
            'Meldung'=>'Position wurde besetzt',
        ]);
    }

    public function removeUser(Positions $positions, User $users)
    {
        $positions->users()->detach($users);

        return redirect()->back()->with([
            'type'=>'success',
            'Meldung'=>'Benutzer von Position entfernt',
        ]);
    }
}
