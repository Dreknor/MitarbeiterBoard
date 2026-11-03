<?php

namespace App\Services\Tasks;

use App\Mail\newTaskMail;
use App\Models\Group;
use App\Models\GroupTaskUser;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Notifications\AufgabeZugewiesen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aufgaben zu Themen (Gruppen-Themen und freie Meeting-Themen).
 *
 * Duplikat-Schutz: Existiert am selben Thema bereits eine offene Aufgabe mit
 * gleichem Text, erhalten Personen, die dafür schon zuständig sind, keine
 * zweite Aufgabe. Gemeinsame Aufgaben desselben Kontextes werden um neue
 * Personen ergänzt statt neu angelegt.
 */
class ThemeTaskService
{
    /**
     * @param  Group|Meeting  $context      Kontext für gemeinsame Aufgaben
     * @param  Collection<int, User>  $assignees  Zuständige Personen
     * @param  bool  $wholeContext  true = "alle" (Gruppe/Teilnehmende), sonst Auswahl
     * @return array{task: ?Task, added: Collection<int, User>, skipped: Collection<int, User>}
     */
    public function create(Theme $theme, Model $context, Collection $assignees, bool $wholeContext, string $text, string $date, User $creator): array
    {
        $assignees = $assignees->filter()->unique('id')->values();
        $text      = trim(preg_replace('/\s+/u', ' ', $text));

        $result = DB::transaction(function () use ($theme, $context, $assignees, $wholeContext, $text, $date, $creator) {
            $duplicates = $this->openDuplicates($theme, $text);
            $coveredIds = $this->coveredUserIds($duplicates);

            $skipped   = $assignees->filter(fn (User $u) => $coveredIds->contains($u->id))->values();
            $remaining = $assignees->reject(fn (User $u) => $coveredIds->contains($u->id))->values();

            if ($remaining->isEmpty()) {
                return ['task' => $duplicates->first(), 'added' => collect(), 'skipped' => $skipped];
            }

            // Genau eine Person ausgewählt → persönliche Aufgabe
            if (! $wholeContext && $assignees->count() === 1) {
                $task = $this->newTask($theme, $text, $date, $creator);
                $remaining->first()->tasks()->save($task);

                return ['task' => $task, 'added' => $remaining, 'skipped' => $skipped];
            }

            // Gemeinsame Aufgabe: bestehende gleiche Aufgabe im selben Kontext ergänzen
            $task = $duplicates->first(fn (Task $t) => $t->taskable_type === $context::class && (int) $t->taskable_id === (int) $context->getKey());

            if (! $task) {
                $task = $this->newTask($theme, $text, $date, $creator);
                $context->tasks()->save($task);
            }

            foreach ($remaining as $user) {
                GroupTaskUser::firstOrCreate(['taskable_id' => $task->id, 'users_id' => $user->id]);
            }

            return ['task' => $task, 'added' => $remaining, 'skipped' => $skipped];
        });

        $this->forgetCaches($result['added']->pluck('id')->all());

        return $result;
    }

    /**
     * Flash-Meldung zum Ergebnis von create().
     *
     * @return array{type: string, Meldung: string}
     */
    public function resultFlash(array $result): array
    {
        $names = fn (Collection $users) => $users->pluck('name')->implode(', ');

        if ($result['added']->isEmpty()) {
            return [
                'type'    => 'warning',
                'Meldung' => 'Diese Aufgabe existiert bereits für ' . $names($result['skipped']) . ' – es wurde keine doppelte Aufgabe angelegt.',
            ];
        }

        $meldung = 'Aufgabe angelegt für ' . $names($result['added']) . '.';
        if ($result['skipped']->isNotEmpty()) {
            $meldung .= ' Bereits zuständig (nicht doppelt angelegt): ' . $names($result['skipped']) . '.';
        }

        return ['type' => 'success', 'Meldung' => $meldung];
    }

    /**
     * Benachrichtigt neu zuständige Personen, außer den Ersteller.
     * Kanäle (Glocke/Push/Mail) nach Einstellung der Person, keine Mail bei Abwesenheit.
     */
    public function notify(Task $task, Collection $users, User $creator): void
    {
        foreach ($users as $user) {
            if ((int) $user->id === (int) $creator->id) {
                continue;
            }

            try {
                $user->notify(new AufgabeZugewiesen($task));
            } catch (\Throwable $e) {
                Log::warning('Aufgaben-Benachrichtigung fehlgeschlagen', [
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Markiert die Aufgabe für die Person als erledigt.
     * Gemeinsame Aufgaben sind erledigt, sobald alle Zuständigen erledigt haben.
     */
    public function complete(Task $task, User $user): bool
    {
        if ($task->completed) {
            return false;
        }

        if ($task->isPersonal()) {
            if ((int) $task->taskable_id !== (int) $user->id) {
                return false;
            }

            $task->update(['completed' => true, 'completed_at' => now(), 'completed_by' => $user->id]);
            $this->forgetCaches([$user->id]);

            return true;
        }

        if (! $task->isCollective()) {
            return false;
        }

        $row = GroupTaskUser::where('taskable_id', $task->id)->where('users_id', $user->id)->first();

        if (! $row) {
            // z. B. später in die Gruppe gekommen – darf trotzdem erledigen
            if (! $this->belongsToContext($task, $user)) {
                return false;
            }
            $row = new GroupTaskUser(['taskable_id' => $task->id, 'users_id' => $user->id]);
        }

        if ($row->completed_at) {
            return false;
        }

        DB::transaction(function () use ($task, $row, $user) {
            $row->completed_at = now();
            $row->save();

            if (! GroupTaskUser::where('taskable_id', $task->id)->whereNull('completed_at')->exists()) {
                $task->update(['completed' => true, 'completed_at' => now(), 'completed_by' => $user->id]);
            }
        });

        $this->forgetCaches($task->taskUsers()->pluck('users_id')->all());

        return true;
    }

    /**
     * Löscht eine Aufgabe (nur Ersteller:in bzw. Ersteller:in des Themas).
     */
    public function canDelete(Task $task, User $user): bool
    {
        return (int) $task->creator_id === (int) $user->id
            || (int) $task->theme?->creator_id === (int) $user->id;
    }

    public function delete(Task $task): void
    {
        $userIds = $task->taskUsers()->pluck('users_id')->all();
        if ($task->isPersonal()) {
            $userIds[] = $task->taskable_id;
        }

        $task->taskUsers()->whereNull('completed_at')->delete();
        $task->delete();

        $this->forgetCaches($userIds);
    }

    /**
     * Alle Aufgaben eines Themas inkl. erledigter, mit Zuständigkeiten.
     */
    public function tasksForTheme(Theme $theme): Collection
    {
        return Task::query()
            ->withCompleted()
            ->where('theme_id', $theme->id)
            ->with(['taskable', 'creator', 'completedBy', 'taskUsers.user'])
            ->orderBy('completed')
            ->orderBy('date')
            ->get()
            ->filter(fn (Task $t) => $t->taskable !== null)
            ->values();
    }

    public function forgetCaches(array $userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $id) {
            Cache::forget('tasks_' . $id);
            Cache::forget('group_tasks_' . $id);
        }
    }

    private function newTask(Theme $theme, string $text, string $date, User $creator): Task
    {
        $task             = new Task(['task' => $text, 'date' => $date]);
        $task->theme_id   = $theme->id;
        $task->creator_id = $creator->id;

        return $task;
    }

    /**
     * Offene Aufgaben am Thema mit gleichem (normalisiertem) Text.
     */
    private function openDuplicates(Theme $theme, string $text): Collection
    {
        $needle = mb_strtolower($text);

        return Task::query()
            ->where('theme_id', $theme->id)
            ->with('taskUsers')
            ->get()
            ->filter(fn (Task $t) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $t->task))) === $needle)
            ->values();
    }

    /**
     * Personen, die für eine der Aufgaben bereits zuständig sind (offen oder erledigt).
     */
    private function coveredUserIds(Collection $tasks): Collection
    {
        return $tasks->flatMap(function (Task $t) {
            if ($t->isPersonal()) {
                return [(int) $t->taskable_id];
            }

            return $t->taskUsers->pluck('users_id')->map(fn ($id) => (int) $id)->all();
        })->unique()->values();
    }

    private function belongsToContext(Task $task, User $user): bool
    {
        return match ($task->taskable_type) {
            Group::class   => $user->groups_rel()->where('groups.id', $task->taskable_id)->exists(),
            Meeting::class => (bool) $task->taskable?->hasParticipant($user),
            default        => false,
        };
    }
}
