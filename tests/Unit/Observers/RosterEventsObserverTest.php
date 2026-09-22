<?php

namespace Tests\Unit\Observers;

use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testet RosterEventsObserver.
 *
 * Bei jedem CRUD-Ereignis muss der Cache-Schlüssel
 * `roster_{roster_id}_{date:Ymd}` invalidiert werden.
 */
class RosterEventsObserverTest extends TestCase
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

    private function cacheKey(int $rosterId, string $date): string
    {
        return 'roster_' . $rosterId . '_' . \Carbon\Carbon::parse($date)->format('Ymd');
    }

    // ─── created ─────────────────────────────────────────────────────────────

    public function test_created_leert_roster_cache(): void
    {
        $roster = Roster::factory()->create();
        $date   = now()->startOfWeek()->format('Y-m-d');
        $key    = $this->cacheKey($roster->id, $date);

        Cache::put($key, 'gecachte-Daten', 300);

        RosterEvents::factory()->for($roster)->create(['date' => $date]);

        $this->assertNull(Cache::get($key), 'Cache wurde nach created nicht geleert.');
    }

    // ─── updated ─────────────────────────────────────────────────────────────

    public function test_updated_leert_roster_cache(): void
    {
        $roster = Roster::factory()->create();
        $date   = now()->startOfWeek()->format('Y-m-d');
        $key    = $this->cacheKey($roster->id, $date);

        $event = RosterEvents::factory()->for($roster)->create(['date' => $date]);

        Cache::put($key, 'gecachte-Daten', 300);
        $event->update(['event' => 'Geändert']);

        $this->assertNull(Cache::get($key), 'Cache wurde nach updated nicht geleert.');
    }

    // ─── deleted ─────────────────────────────────────────────────────────────

    public function test_deleted_leert_roster_cache(): void
    {
        $roster = Roster::factory()->create();
        $date   = now()->startOfWeek()->format('Y-m-d');
        $key    = $this->cacheKey($roster->id, $date);

        $event = RosterEvents::factory()->for($roster)->create(['date' => $date]);

        Cache::put($key, 'gecachte-Daten', 300);
        $event->delete();

        $this->assertNull(Cache::get($key), 'Cache wurde nach deleted nicht geleert.');
    }

    // ─── restored ────────────────────────────────────────────────────────────

    public function test_restored_leert_roster_cache(): void
    {
        $roster = Roster::factory()->create();
        $date   = now()->startOfWeek()->format('Y-m-d');
        $key    = $this->cacheKey($roster->id, $date);

        $event = RosterEvents::factory()->for($roster)->create(['date' => $date]);
        $event->delete();

        Cache::put($key, 'gecachte-Daten', 300);
        $event->restore();

        $this->assertNull(Cache::get($key), 'Cache wurde nach restored nicht geleert.');
    }

    // ─── forceDeleted ────────────────────────────────────────────────────────

    public function test_force_deleted_leert_roster_cache(): void
    {
        $roster = Roster::factory()->create();
        $date   = now()->startOfWeek()->format('Y-m-d');
        $key    = $this->cacheKey($roster->id, $date);

        $event = RosterEvents::factory()->for($roster)->create(['date' => $date]);
        $event->delete();

        Cache::put($key, 'gecachte-Daten', 300);
        $event->forceDelete();

        $this->assertNull(Cache::get($key), 'Cache wurde nach forceDeleted nicht geleert.');
    }
}

