<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use Tests\TestCase;

class OxCalendarTest extends TestCase
{
    /** @test */
    public function ox_calendar_kann_erstellt_werden(): void
    {
        $calendar = OxCalendar::factory()->create();

        $this->assertInstanceOf(OxCalendar::class, $calendar);
        $this->assertTrue($calendar->sichtbar);
        $this->assertFalse($calendar->schreibbar);
    }

    /** @test */
    public function ox_calendar_hat_termine_relation(): void
    {
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->count(3)->create(['ox_calendar_id' => $calendar->id]);

        $this->assertCount(3, $calendar->termine);
    }

    /** @test */
    public function ox_calendar_hat_groups_relation_mit_pivot(): void
    {
        $calendar = OxCalendar::factory()->create();
        $group    = Group::factory()->create();
        $calendar->groups()->attach($group->id, ['schreibbar' => true]);

        $this->assertCount(1, $calendar->groups);
        $this->assertTrue((bool) $calendar->groups->first()->pivot->schreibbar);
    }

    /** @test */
    public function ox_calendar_hat_sync_logs_relation(): void
    {
        $calendar = OxCalendar::factory()->create();
        OxSyncLog::factory()->count(2)->create(['ox_calendar_id' => $calendar->id]);

        $this->assertCount(2, $calendar->syncLogs);
    }

    /** @test */
    public function ox_calendar_soft_deletes_funktioniert(): void
    {
        $calendar = OxCalendar::factory()->create();
        $calendar->delete();

        $this->assertNull(OxCalendar::find($calendar->id));
        $this->assertNotNull(OxCalendar::withTrashed()->find($calendar->id));
    }
}

