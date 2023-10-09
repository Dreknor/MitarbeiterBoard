<?php

namespace App\View\Composers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
        $tasks = auth()->user()->tasks;
        $group_tasks =  auth()->user()->group_tasks;
        //$groups = auth()->user()->groups();

        foreach ($group_tasks as $group_task){
            $tasks = $tasks->push($group_task->task);
        }
/*
        foreach ($groups as $group) {
            if ($group->proteced or auth()->user()->groups_rel->contains('id', $group->id)) {
                $group_tasks=$group->tasks()->whereDate('date', '>=', Carbon::now())->whereHas('taskUsers', function (Builder $query) {
                    $query->where('users_id', auth()->id());
                })->get();

                $tasks = $tasks->concat($group_tasks);
            }
        }
*/
        $view->with(['tasks' => $tasks]);
    }
}
