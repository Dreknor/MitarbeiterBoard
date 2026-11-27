<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\Klasse;
use App\Models\Meeting;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Schueler;
use App\Models\Task;
use App\Models\Theme;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Vertretung;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests für das Meeting-Model (2.6)
 */
class TaskTest extends TestCase
{
    // ─── Polymorphe Relation ──────────────────────────────────────────────────

    public function test_task_hat_polymorphe_taskable_relation_mit_user(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'taskable')->create();

        $this->assertEquals($user->id, Task::withoutGlobalScopes()->find($task->id)->taskable->id);
        $this->assertInstanceOf(User::class, Task::withoutGlobalScopes()->find($task->id)->taskable);
    }

    public function test_task_hat_polymorphe_taskable_relation_mit_group(): void
    {
        $group = Group::factory()->create();
        $task  = Task::factory()->for($group, 'taskable')->create();

        $this->assertEquals($group->id, Task::withoutGlobalScopes()->find($task->id)->taskable->id);
        $this->assertInstanceOf(Group::class, Task::withoutGlobalScopes()->find($task->id)->taskable);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_task_completed_ist_boolean(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->completed()->for($user, 'taskable')->create();

        $this->assertIsBool(Task::withoutGlobalScopes()->find($task->id)->completed);
        $this->assertTrue(Task::withoutGlobalScopes()->find($task->id)->completed);
    }

    // ─── GlobalScope ─────────────────────────────────────────────────────────

    public function test_task_global_scope_schliesst_completed_aus(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'taskable')->create(['completed' => false]);
        Task::factory()->completed()->for($user, 'taskable')->create();

        // Standard-Query (mit globalem Scope) gibt nur nicht-abgeschlossene zurück
        $this->assertCount(1, Task::all());
    }

    public function test_task_ohne_global_scope_zeigt_alle(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'taskable')->create(['completed' => false]);
        Task::factory()->completed()->for($user, 'taskable')->create();

        $this->assertCount(2, Task::withoutGlobalScopes()->get());
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_task_wird_soft_deleted(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'taskable')->create();
        $id   = $task->id;

        $task->delete();

        // Normales find() wendet SoftDeletingScope an → nicht auffindbar
        $this->assertNull(Task::find($id));
        // withoutGlobalScopes() entfernt ALLE Scopes inkl. SoftDeletes → sichtbar
        $this->assertNotNull(Task::withoutGlobalScopes()->find($id));
    }
}

/**
 * Tests für das Ticket-Model (2.6)
 */
