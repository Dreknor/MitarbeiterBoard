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
class SchuelerTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_schueler_hat_klasse_relation(): void
    {
        $klasse   = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $this->assertEquals($klasse->id, $schueler->klasse->id);
    }

    public function test_schueler_hat_wp_plaene_relation(): void
    {
        $schueler  = Schueler::factory()->create();
        $parent    = \App\Models\Wochenplan\WpPlan::factory()->alsKlassenplan()->create();
        \App\Models\Wochenplan\WpPlan::factory()
            ->alsSchuelerplan($parent)
            ->create(['schueler_id' => $schueler->id]);

        $this->assertCount(1, $schueler->wpPlaene);
    }

    // ─── Accessor ────────────────────────────────────────────────────────────

    public function test_schueler_name_accessor(): void
    {
        $schueler = Schueler::factory()->create([
            'vorname'  => 'Max',
            'nachname' => 'Muster',
        ]);

        $this->assertEquals('Max Muster', $schueler->name);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_schueler_wird_soft_deleted(): void
    {
        $schueler = Schueler::factory()->create();
        $id       = $schueler->id;

        $schueler->delete();

        $this->assertNull(Schueler::find($id));
        $this->assertNotNull(Schueler::withTrashed()->find($id));
    }
}

/**
 * Tests für das Room/RoomBooking-Model (2.6)
 */
