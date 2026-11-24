<?php

namespace App\Observers;

use App\Models\ErgebnisKarte;
use App\Models\GroupTaskUser;
use App\Models\Task;
use App\Services\Ergebnisboards\ErgebnisboardAufgabenService;

/**
 * Hält Karten auf Ergebnisboards mit ihrer Aufgabe in Einklang (Spalte je Status).
 * Beobachtet Task (erledigt) und GroupTaskUser (Teil-Erledigung gemeinsamer Aufgaben).
 */
class ErgebnisKarteAufgabeObserver
{
    public function saved(Task|GroupTaskUser $model): void
    {
        $taskId = $model instanceof Task ? $model->id : $model->taskable_id;
        if (! $taskId || ! ErgebnisKarte::query()->where('task_id', $taskId)->exists()) {
            return;
        }
        if ($model instanceof Task && ! $model->wasChanged(['completed', 'completed_at']) && ! $model->wasRecentlyCreated) {
            return;
        }

        $task = Task::withCompleted()->find($taskId);
        if ($task) {
            app(ErgebnisboardAufgabenService::class)->synchronisieren($task);
        }
    }
}
