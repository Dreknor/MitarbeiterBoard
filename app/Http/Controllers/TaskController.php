<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreThemeTaskRequest;
use App\Models\Group;
use App\Models\Task;
use App\Models\Theme;
use App\Services\Tasks\ThemeTaskService;
use Illuminate\Http\RedirectResponse;

class TaskController extends Controller
{
    public function __construct(private readonly ThemeTaskService $tasks)
    {
    }

    /**
     * Aufgabe zu einem Gruppen-Thema anlegen (ganze Gruppe oder ausgewählte Mitglieder).
     */
    public function store($groupname, Theme $theme, StoreThemeTaskRequest $request): RedirectResponse
    {
        $group = Group::where('name', $groupname)->first();

        if (! $group || ! auth()->user()->groups()->contains('id', $group->id) || (int) $theme->group_id !== (int) $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $members = $group->users;

        if ($request->wholeContext()) {
            $assignees = $members;
        } else {
            $assignees = $members->whereIn('id', $request->userIds())->values();
            if ($assignees->count() !== count($request->userIds())) {
                return redirect()->back()->withInput()->with([
                    'type'    => 'warning',
                    'Meldung' => 'Mindestens eine ausgewählte Person ist nicht in der Gruppe.',
                ]);
            }
        }

        $result = $this->tasks->create($theme, $group, $assignees, $request->wholeContext(), $request->input('task'), $request->input('date'), auth()->user());

        if ($result['added']->isNotEmpty()) {
            $this->tasks->notify($result['task'], $result['added'], auth()->user());
        }

        return redirect()->back()->with($this->tasks->resultFlash($result));
    }

    /**
     * Aufgabe als erledigt markieren (persönlich oder eigener Anteil einer gemeinsamen Aufgabe).
     */
    public function complete(Task $task): RedirectResponse
    {
        $done = $this->tasks->complete($task, auth()->user());

        return redirect()->back()->with($done
            ? ['type' => 'success', 'Meldung' => 'Aufgabe erledigt']
            : ['type' => 'warning', 'Meldung' => 'Diese Aufgabe kannst du nicht als erledigt markieren.']);
    }

    public function destroy(Task $task): RedirectResponse
    {
        abort_unless($this->tasks->canDelete($task, auth()->user()), 403);

        $this->tasks->delete($task);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Aufgabe gelöscht',
        ]);
    }
}
