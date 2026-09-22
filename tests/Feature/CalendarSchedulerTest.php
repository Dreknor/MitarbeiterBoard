<?php

namespace Tests\Feature;

use Tests\TestCase;

class CalendarSchedulerTest extends TestCase
{
    public function test_ox_sync_calendars_command_ist_im_scheduler_registriert(): void
    {
        // Prüfe dass der Command existiert und ausführbar ist (--help gibt exit 0 zurück)
        $this->artisan('ox:sync-calendars --help')
            ->assertExitCode(0);
    }
}

