<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\Task;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Offene Aufgaben (persönlich und gemeinsam), die am Zieltag fällig oder überfällig sind.
 */
class AufgabenQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'aufgaben';
    }

    public function label(): string
    {
        return 'Fällige Aufgaben';
    }

    public function icon(): string
    {
        return 'fa-tasks';
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        // Wie TasksComposer: persönliche Aufgaben + offene Zuständigkeiten bei gemeinsamen Aufgaben
        $eigene = $user->tasks()->with('theme')->get();
        $gemeinsam = $user->group_tasks()->with('task.theme')->get()->pluck('task');

        return $eigene->concat($gemeinsam)
            ->filter(fn (?Task $task) => $task !== null && $task->date !== null && $task->date->lte($tag->copy()->endOfDay()))
            ->unique('id')
            ->sortBy('date')
            ->values()
            ->map(fn (Task $task) => new TagesvorschauEintrag(
                titel: (string) $task->task,
                zeit: $task->date->isSameDay($tag) ? 'heute' : 'seit '.$task->date->format('d.m.'),
                details: $task->theme?->theme,
                url: $task->themeUrl(),
                hervorheben: $task->date->lt($tag->copy()->startOfDay()),
            ));
    }
}
