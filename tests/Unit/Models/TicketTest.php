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
class TicketTest extends TestCase
{
    private function createTicket(array $attrs = []): Ticket
    {
        $user = User::factory()->create();
        $cat  = TicketCategory::factory()->create();
        return Ticket::factory()->for($user, 'user')->for($cat, 'category')->create($attrs);
    }

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_ticket_hat_user_relation(): void
    {
        $ticket = $this->createTicket();
        $this->assertInstanceOf(User::class, $ticket->user);
    }

    public function test_ticket_hat_category_relation(): void
    {
        $ticket = $this->createTicket();
        $this->assertInstanceOf(TicketCategory::class, $ticket->category);
    }

    public function test_ticket_hat_comments_relation(): void
    {
        $ticket  = $this->createTicket();
        $author  = User::factory()->create();
        TicketComment::factory()->for($ticket)->for($author, 'user')->create();

        $this->assertCount(1, $ticket->comments);
    }

    public function test_ticket_hat_assigned_relation(): void
    {
        $assignee = User::factory()->create();
        $ticket   = $this->createTicket(['assigned_to' => $assignee->id]);

        $this->assertEquals($assignee->id, $ticket->assigned->id);
    }

    // ─── Status ──────────────────────────────────────────────────────────────

    public function test_ticket_scopeOpen_gibt_offene_tickets(): void
    {
        $user = User::factory()->create();
        $cat  = TicketCategory::factory()->create();

        Ticket::factory()->open()->for($user, 'user')->for($cat, 'category')->create();
        Ticket::factory()->closed()->for($user, 'user')->for($cat, 'category')->create();

        $result = Ticket::open()->get();
        $this->assertCount(1, $result);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_ticket_wird_soft_deleted(): void
    {
        $ticket = $this->createTicket();
        $id     = $ticket->id;

        $ticket->delete();

        $this->assertNull(Ticket::find($id));
        $this->assertNotNull(Ticket::withTrashed()->find($id));
    }
}

/**
 * Tests für das Schueler-Model (2.6)
 */
