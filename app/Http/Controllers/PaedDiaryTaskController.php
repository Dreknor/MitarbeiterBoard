<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\PaedDiaryHelperTrait;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Services\PaedDiaryTaskService;
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
                foreach ($this->tasks()->createForStudents($klasse->id, $ids, $data, $user->id, $highlighted) as $task) {
                    $created[] = $this->brief($task);
                }
            }
            return response()->json(['success' => true, 'tasks' => $created]);
        }

        if ($request->filled('klasse_id')) {
            $klasse = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
            $ids    = !empty($schuelerIds)
                ? Schueler::whereIn('id', $schuelerIds)->where('klasse_id', $klasse->id)->pluck('id')->all()
                : Schueler::where('klasse_id', $klasse->id)->pluck('id')->all();
            if (empty($ids)) return response()->json(['message' => 'Keine gültigen Schüler'], 422);
            foreach ($this->tasks()->createForStudents($klasse->id, $ids, $data, $user->id, $highlighted) as $task) {
                $created[] = $this->brief($task);
            }
            return response()->json(['success' => true, 'tasks' => $created]);
        }

        if (!empty($schuelerIds)) {
            $validSchueler = Schueler::whereIn('id', $schuelerIds)->get(['id', 'klasse_id'])->filter(fn ($s) => in_array($s->klasse_id, $allowedClassIds));
            if ($validSchueler->isEmpty()) return response()->json(['message' => 'Keine gültigen Schüler'], 422);
            foreach ($validSchueler as $s) {
                foreach ($this->tasks()->createForStudents((int) $s->klasse_id, [$s->id], $data, $user->id, $highlighted) as $task) {
                    $created[] = $this->brief($task);
                }
            }
            return response()->json(['success' => true, 'tasks' => $created]);
        }

        return response()->json(['message' => 'Keine Schüler angegeben'], 422);
    }

    public function closeTask(PaedDiaryTask $task)
    {
        Auth::user()->paed_klassen()->where('klassen.id', $task->klasse_id)->firstOrFail();
        $this->tasks()->close($task);
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
        $this->tasks()->update($task, $data);
        return response()->json(['success' => true, 'task' => ['id' => $task->id, 'schueler_id' => $task->schueler_id, 'title' => $task->title, 'description' => $task->description, 'due_date' => $task->due_date?->toDateString(), 'highlighted' => $task->highlighted, 'klasse_id' => $task->klasse_id]]);
    }

    private function tasks(): PaedDiaryTaskService
    {
        return app(PaedDiaryTaskService::class);
    }

    /** Antwortformat wie bisher (ohne Beschreibung). */
    private function brief(PaedDiaryTask $task): array
    {
        return ['id' => $task->id, 'schueler_id' => $task->schueler_id, 'title' => $task->title, 'due_date' => $task->due_date?->toDateString(), 'highlighted' => $task->highlighted, 'klasse_id' => $task->klasse_id];
    }
}
