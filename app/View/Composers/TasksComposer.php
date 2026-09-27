<?php

namespace App\View\Composers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TasksComposer
{
    /**
     *
     */
    public function __construct()
    {

    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $tasks = Cache::remember('tasks_'.auth()->id(), Carbon::now()->addMinutes(5), function () {
            return auth()->user()->tasks;
        });

        $group_tasks = Cache::remember('group_tasks_'.auth()->id(), Carbon::now()->addMinutes(5), function () {
            return auth()->user()->group_tasks()->with('task.theme')->get();
        });

        // Gemeinsame Aufgaben ergänzen; erledigte/gelöschte (null) und doppelte Einträge entfernen
        $tasks = collect($tasks)
            ->concat($group_tasks->pluck('task'))
            ->filter()
            ->unique('id')
            ->values();

        $view->with(['tasks' => $tasks]);
    }
}
