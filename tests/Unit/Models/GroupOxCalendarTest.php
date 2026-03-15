<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\OxCalendar;
use Tests\TestCase;

class GroupOxCalendarTest extends TestCase
{
    /** @test */
    public function group_hat_ox_calendars_relation(): void
    {
        $group    = Group::factory()->create();
        $calendar = OxCalendar::factory()->create();
        $group->oxCalendars()->attach($calendar->id, ['schreibbar' => false]);

        $this->assertCount(1, $group->oxCalendars);
        $this->assertFalse((bool) $group->oxCalendars->first()->pivot->schreibbar);
    }
}

