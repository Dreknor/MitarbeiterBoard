<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\PaedDiaryHelperTrait;
use App\Models\PaedDiaryClassGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaedDiaryClassGroupController extends Controller
{
    use PaedDiaryHelperTrait;

    public function index(Request $request)
    {
        $user   = Auth::user();
        $groups = PaedDiaryClassGroup::with('klassen:id,name,kuerzel')
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'id'      => $g->id,
                'name'    => $g->name,
                'klassen' => $g->klassen->map(fn ($k) => ['id' => $k->id, 'name' => $k->name, 'kuerzel' => $k->kuerzel]),
            ]);
        return response()->json(['groups' => $groups]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'klasse_ids'    => ['required', 'array', 'min:2'],
            'klasse_ids.*'  => ['integer', 'exists:klassen,id'],
        ]);
        $user       = Auth::user();
        $allowedIds = $user->paed_klassen()->pluck('klassen.id')->toArray();
        $klasseIds  = array_values(array_unique(array_intersect($data['klasse_ids'], $allowedIds)));
        if (count($klasseIds) < 2) return response()->json(['message' => 'Mindestens 2 gültige Klassen erforderlich'], 422);
        $group = PaedDiaryClassGroup::create(['user_id' => $user->id, 'name' => trim($data['name'])]);
        $group->klassen()->sync($klasseIds);
        $group->load('klassen:id,name,kuerzel');
        return response()->json(['success' => true, 'group' => ['id' => $group->id, 'name' => $group->name, 'klassen' => $group->klassen->map(fn ($k) => ['id' => $k->id, 'name' => $k->name, 'kuerzel' => $k->kuerzel])]]);
    }

    public function update(PaedDiaryClassGroup $group, Request $request)
    {
        $user = Auth::user();
        abort_unless($group->user_id === $user->id, 403);
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'klasse_ids'   => ['required', 'array', 'min:2'],
            'klasse_ids.*' => ['integer', 'exists:klassen,id'],
        ]);
        $allowedIds = $user->paed_klassen()->pluck('klassen.id')->toArray();
        $klasseIds  = array_values(array_unique(array_intersect($data['klasse_ids'], $allowedIds)));
        if (count($klasseIds) < 2) return response()->json(['message' => 'Mindestens 2 gültige Klassen erforderlich'], 422);
        $group->update(['name' => trim($data['name'])]);
        $group->klassen()->sync($klasseIds);
        return response()->json(['success' => true]);
    }

    public function destroy(PaedDiaryClassGroup $group)
    {
        $user = Auth::user();
        abort_unless($group->user_id === $user->id, 403);
        $group->klassen()->detach();
        $group->delete();
        return response()->json(['success' => true]);
    }
}

