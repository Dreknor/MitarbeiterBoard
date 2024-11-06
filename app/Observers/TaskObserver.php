<?php

namespace App\Observers;

use App\Models\Task;
use Illuminate\Support\Facades\Cache;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
       if ($task->taskable_type === 'App\Models\GroupTask') {
           foreach ($task->taskable->group->users as $user) {
                Cache::forget('tasks_'.$user->id);
           }
       } else {
           Cache::forget('tasks_' . $task->taskable_id);
       }
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        if ($task->taskable_type === 'App\Models\GroupTask') {
            foreach ($task->taskable->group->users as $user) {
                Cache::forget('tasks_'.$user->id);
            }
        } else {
            Cache::forget('tasks_' . $task->taskable_id);
        }
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        if ($task->taskable_type === 'App\Models\GroupTask') {
            foreach ($task->taskable->group->users as $user) {
                Cache::forget('tasks_'.$user->id);
            }
        } else {
            Cache::forget('tasks_' . $task->taskable_id);
        }
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        if ($task->taskable_type === 'App\Models\GroupTask') {
            foreach ($task->taskable->group->users as $user) {
                Cache::forget('tasks_'.$user->id);
            }
        } else {
            Cache::forget('tasks_' . $task->taskable_id);
        }
    }

    /**
     * Handle the Task "force deleted" event.
     */
    public function forceDeleted(Task $task): void
    {
        if ($task->taskable_type === 'App\Models\GroupTask') {
            foreach ($task->taskable->group->users as $user) {
                Cache::forget('tasks_'.$user->id);
            }
        } else {
            Cache::forget('tasks_' . $task->taskable_id);
        }
    }
}
