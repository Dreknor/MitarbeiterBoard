<?php

namespace App\Observers;

use App\Models\Task;
use Illuminate\Support\Facades\Cache;

/**
 * Leert die Aufgaben-Caches (Dashboard) aller betroffenen Personen.
 */
class TaskObserver
{
    public function created(Task $task): void
    {
        $this->forget($task);
    }

    public function updated(Task $task): void
    {
        $this->forget($task);
    }

    public function deleted(Task $task): void
    {
        $this->forget($task);
    }

    public function restored(Task $task): void
    {
        $this->forget($task);
    }

    public function forceDeleted(Task $task): void
    {
        $this->forget($task);
    }

    private function forget(Task $task): void
    {
        if ($task->isPersonal()) {
            Cache::forget('tasks_' . $task->taskable_id);

            return;
        }

        foreach ($task->taskUsers()->pluck('users_id') as $userId) {
            Cache::forget('group_tasks_' . $userId);
        }
    }
}
