<?php

namespace Tests\Unit\Commands;

use App\Models\OxCalendar;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests für den ox:sync-calendars Artisan-Command.
 * Entspricht TODO 06 der calendar-ox-Reihe.
 */
class OxSyncCalendarsTest extends TestCase
{
    /** Leere multistatus-Antwort (kein Event, kein sync-token) */
    private string $emptyMultistatus = '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"></d:multistatus>';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ox-calendar.url'        => 'https://ox.example.com/caldav',
            'ox-calendar.username'   => 'testuser',
            'ox-calendar.password'   => 'testpass',
            'ox-calendar.enabled'    => true,
            'ox-calendar.verify_ssl' => true,
            'ox-calendar.timeout'    => 30,
        ]);
    }

    public function test_ox_sync_calendars_zeigt_Warnung_wenn_deaktiviert(): void
    {
        config(['ox-calendar.enabled' => false]);

        $this->artisan('ox:sync-calendars')
            ->expectsOutputToContain('deaktiviert')
            ->assertExitCode(0);
    }

    public function test_ox_sync_calendars_synchronisiert_Kalender(): void
    {
        OxCalendar::factory()->create(['name' => 'Testkalender', 'sichtbar' => true]);

        Http::fake([
            '*' => Http::response($this->emptyMultistatus, 207),
        ]);

        $this->artisan('ox:sync-calendars')
            ->expectsOutputToContain('Synchronisation abgeschlossen')
            ->assertExitCode(0);
    }

    public function test_ox_sync_calendars_mit_calendar_filtert_korrekt(): void
    {
        OxCalendar::factory()->create(['name' => 'Schulkalender', 'sichtbar' => true]);
        OxCalendar::factory()->create(['name' => 'Anderer', 'sichtbar' => true]);

        Http::fake([
            '*' => Http::response($this->emptyMultistatus, 207),
        ]);

        $this->artisan('ox:sync-calendars --calendar=Schulkalender')
            ->expectsOutputToContain('Schulkalender')
            ->assertExitCode(0);
    }

    public function test_ox_sync_calendars_gibt_Fehler_bei_unbekanntem_Kalender(): void
    {
        $this->artisan('ox:sync-calendars --calendar=Unbekannt')
            ->expectsOutputToContain('nicht gefunden')
            ->assertExitCode(1);
    }

    public function test_ox_sync_calendars_mit_force_umgeht_Deaktivierung(): void
    {
        config(['ox-calendar.enabled' => false]);
        OxCalendar::factory()->create(['sichtbar' => true]);

        Http::fake([
            '*' => Http::response($this->emptyMultistatus, 207),
        ]);

        $this->artisan('ox:sync-calendars --force')
            ->expectsOutputToContain('Synchronisation')
            ->assertExitCode(0);
    }
}

