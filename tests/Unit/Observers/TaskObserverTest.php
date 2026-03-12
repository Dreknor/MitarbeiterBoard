<?php

namespace Tests\Unit\Observers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testet TaskObserver.
 *
 * Bei jedem CRUD-Ereignis wird `tasks_{taskable_id}` aus dem Cache entfernt
 * (für direkte User-Tasks). Der GroupTask-Pfad wird separat markiert –
 * das Model `App\Models\GroupTask` existiert aktuell nicht, daher wird er
 * nur in der Fehlerbehandlung berücksichtigt.
 */
class TaskObserverTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->actingAs($this->actor);
        Cache::flush();
    }

    // ─── Hilfsmethode ────────────────────────────────────────────────────────

    /**
     * Erstellt eine Task, die einem User zugeordnet ist (non-GroupTask-Pfad).
     */
    private function makeUserTask(User $user): Task
    {
        return Task::factory()->create([
            'taskable_type' => \App\Models\User::class,
            'taskable_id'   => $user->id,
        ]);
    }

    // ─── created ─────────────────────────────────────────────────────────────

    public function test_created_user_task_leert_user_cache(): void
    {
        $user = User::factory()->create();
        $key  = 'tasks_' . $user->id;

        Cache::put($key, ['aufgaben'], 300);

        $this->makeUserTask($user);

        $this->assertNull(Cache::get($key), 'tasks-Cache wurde nach created nicht geleert.');
    }

    // ─── updated ─────────────────────────────────────────────────────────────

    public function test_updated_user_task_leert_user_cache(): void
    {
        $user = User::factory()->create();
        $key  = 'tasks_' . $user->id;

        // Task ohne withoutGlobalScopes anlegen, damit completed=false bleibt
        $task = $this->makeUserTask($user);

        Cache::put($key, ['aufgaben'], 300);

        $task->update(['task' => 'Geänderter Aufgabentext']);

        $this->assertNull(Cache::get($key), 'tasks-Cache wurde nach updated nicht geleert.');
    }

    // ─── deleted ─────────────────────────────────────────────────────────────

    public function test_deleted_user_task_leert_user_cache(): void
    {
        $user = User::factory()->create();
        $key  = 'tasks_' . $user->id;

        $task = $this->makeUserTask($user);

        Cache::put($key, ['aufgaben'], 300);

        $task->delete();

        $this->assertNull(Cache::get($key), 'tasks-Cache wurde nach deleted nicht geleert.');
    }

    // ─── restored ────────────────────────────────────────────────────────────

    public function test_restored_user_task_leert_user_cache(): void
    {
        $user = User::factory()->create();
        $key  = 'tasks_' . $user->id;

        $task = $this->makeUserTask($user);
        $task->delete();

        Cache::put($key, ['aufgaben'], 300);

        $task->restore();

        $this->assertNull(Cache::get($key), 'tasks-Cache wurde nach restored nicht geleert.');
    }

    // ─── forceDeleted ────────────────────────────────────────────────────────

    public function test_force_deleted_user_task_leert_user_cache(): void
    {
        $user = User::factory()->create();
        $key  = 'tasks_' . $user->id;

        $task = $this->makeUserTask($user);
        $task->delete();

        Cache::put($key, ['aufgaben'], 300);

        $task->forceDelete();

        $this->assertNull(Cache::get($key), 'tasks-Cache wurde nach forceDeleted nicht geleert.');
    }

}


