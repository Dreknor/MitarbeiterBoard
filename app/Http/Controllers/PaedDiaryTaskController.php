<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\PaedDiaryHelperTrait;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaedDiaryTaskController extends Controller
{
    use PaedDiaryHelperTrait;

    public function store(Request $request)
    {
        $data = $request->validate([
            'klasse_id'    => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id'     => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'schueler_id'  => ['nullable', 'integer', 'exists:schueler,id'],
            'schueler_ids' => ['nullable', 'array', 'min:1'],
            'schueler_ids.*' => ['integer', 'exists:schueler,id'],
            'title'        => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string'],
            'due_date'     => ['nullable', 'date'],
            'highlighted'  => ['nullable', 'boolean'],
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);
        }
        $user        = Auth::user();
        $highlighted = $data['highlighted'] ?? true;
        $created     = [];

        $schuelerIds = [];
        if (!empty($data['schueler_ids'])) {
            $schuelerIds = array_values(array_unique($data['schueler_ids']));
        } elseif (!empty($data['schueler_id'])) {
            $schuelerIds = [$data['schueler_id']];
        }

        $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->toArray();

        if ($request->filled('group_id')) {
            $group          = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            foreach ($group->klassen->whereIn('id', $userKlassenIds) as $klasse) {
                $ids = !empty($schuelerIds)
                    ? Schueler::whereIn('id', $schuelerIds)->where('klasse_id', $klasse->id)->pluck('id')->all()
                    : Schueler::where('klasse_id', $klasse->id)->pluck('id')->all();
                if (empty($ids)) continue;
                foreach ($ids as $sid) {
                    $task      = PaedDiaryTask::create(['klasse_id' => $klasse->id, 'schueler_id' => $sid, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'due_date' => $data['due_date'] ?? null, 'status' => 'open', 'highlighted' => $highlighted, 'created_by' => $user->id]);
                    $created[] = ['id' => $task->id, 'schueler_id' => $task->schueler_id, 'title' => $task->title, 'due_date' => $task->due_date?->toDateString(), 'highlighted' => $task->highlighted, 'klasse_id' => $task->klasse_id];
                }
                $this->forgetWeekCache($klasse->id, Carbon::now());
            }
            return response()->json(['success' => true, 'tasks' => $created]);
        }

        if ($request->filled('klasse_id')) {
            $klasse = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
            $ids    = !empty($schuelerIds)
                ? Schueler::whereIn('id', $schuelerIds)->where('klasse_id', $klasse->id)->pluck('id')->all()
                : Schueler::where('klasse_id', $klasse->id)->pluck('id')->all();
            if (empty($ids)) return response()->json(['message' => 'Keine gültigen Schüler'], 422);
            foreach ($ids as $sid) {
                $task      = PaedDiaryTask::create(['klasse_id' => $klasse->id, 'schueler_id' => $sid, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'due_date' => $data['due_date'] ?? null, 'status' => 'open', 'highlighted' => $highlighted, 'created_by' => $user->id]);
                $created[] = ['id' => $task->id, 'schueler_id' => $task->schueler_id, 'title' => $task->title, 'due_date' => $task->due_date?->toDateString(), 'highlighted' => $task->highlighted, 'klasse_id' => $task->klasse_id];
            }
            $this->forgetWeekCache($klasse->id, Carbon::now());
            return response()->json(['success' => true, 'tasks' => $created]);
        }

        if (!empty($schuelerIds)) {
            $validSchueler = Schueler::whereIn('id', $schuelerIds)->get(['id', 'klasse_id'])->filter(fn ($s) => in_array($s->klasse_id, $allowedClassIds));
            if ($validSchueler->isEmpty()) return response()->json(['message' => 'Keine gültigen Schüler'], 422);
            foreach ($validSchueler as $s) {
                $task      = PaedDiaryTask::create(['klasse_id' => $s->klasse_id, 'schueler_id' => $s->id, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'due_date' => $data['due_date'] ?? null, 'status' => 'open', 'highlighted' => $highlighted, 'created_by' => $user->id]);
                $created[] = ['id' => $task->id, 'schueler_id' => $task->schueler_id, 'title' => $task->title, 'due_date' => $task->due_date?->toDateString(), 'highlighted' => $task->highlighted, 'klasse_id' => $task->klasse_id];
                $this->forgetWeekCache($s->klasse_id, Carbon::now());
            }
            return response()->json(['success' => true, 'tasks' => $created]);
        }

        return response()->json(['message' => 'Keine Schüler angegeben'], 422);
    }

    public function closeTask(PaedDiaryTask $task)
    {
        Auth::user()->paed_klassen()->where('klassen.id', $task->klasse_id)->firstOrFail();
        $task->update(['status' => 'closed', 'highlighted' => false, 'closed_at' => now()]);
        $this->forgetWeekCache($task->klasse_id, Carbon::now());
        return response()->json(['success' => true]);
    }

    public function updateTask(Request $request, PaedDiaryTask $task)
    {
        Auth::user()->paed_klassen()->where('klassen.id', $task->klasse_id)->firstOrFail();
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'due_date'    => ['nullable', 'date'],
            'highlighted' => ['nullable', 'boolean'],
        ]);
        $task->update(['title' => $data['title'], 'description' => $data['description'] ?? null, 'due_date' => $data['due_date'] ?? null, 'highlighted' => $data['highlighted'] ?? $task->highlighted]);
        $this->forgetWeekCache($task->klasse_id, Carbon::now());
        return response()->json(['success' => true, 'task' => ['id' => $task->id, 'schueler_id' => $task->schueler_id, 'title' => $task->title, 'description' => $task->description, 'due_date' => $task->due_date?->toDateString(), 'highlighted' => $task->highlighted, 'klasse_id' => $task->klasse_id]]);
    }
}

