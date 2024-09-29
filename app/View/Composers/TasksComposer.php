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
            return auth()->user()->group_tasks;
        });

        foreach ($group_tasks as $group_task){
            $tasks = $tasks->push($group_task->task);
        }

        $view->with(['tasks' => $tasks]);
    }
}
