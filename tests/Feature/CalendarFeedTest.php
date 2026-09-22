<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CalendarFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_ical_feed_ist_mit_gueltigem_token_erreichbar(): void
    {
        $user = User::factory()->create(['calendar_token' => 'valid-test-token-123']);
        Permission::findOrCreate('view calendar');
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Test-Termin',
            'beginn'         => now()->addDay(),
            'ende'           => now()->addDay()->addHour(),
        ]);

        $this->get('/calendar/feed/valid-test-token-123.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('Test-Termin');
    }

    public function test_ical_feed_gibt_404_bei_ungueltigem_token(): void
    {
        $this->get('/calendar/feed/invalid-token.ics')
            ->assertNotFound();
    }

    public function test_ical_feed_enthaelt_nur_sichtbare_kalender_des_users(): void
    {
        $user = User::factory()->create(['calendar_token' => 'token-sichtbarkeit']);
        Permission::findOrCreate('view calendar');
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $group = Group::factory()->create();
        $user->groups_rel()->attach($group);

        // Kalender mit Gruppen-Zuordnung (User hat Zugang)
        $meinKalender = OxCalendar::factory()->create();
        $meinKalender->groups()->attach($group->id, ['schreibbar' => false]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $meinKalender->id,
            'titel'          => 'Mein Termin',
            'beginn'         => now()->addDay(),
            'ende'           => now()->addDay()->addHour(),
        ]);

        // Kalender mit anderer Gruppe (User hat keinen Zugang)
        $andereGruppe = Group::factory()->create();
        $andererKalender = OxCalendar::factory()->create();
        $andererKalender->groups()->attach($andereGruppe->id, ['schreibbar' => false]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $andererKalender->id,
            'titel'          => 'Fremder Termin',
            'beginn'         => now()->addDay(),
            'ende'           => now()->addDay()->addHour(),
        ]);

        $response = $this->get('/calendar/feed/token-sichtbarkeit.ics');
        $response->assertSee('Mein Termin');
        $response->assertDontSee('Fremder Termin');
    }

    public function test_feed_token_kann_generiert_werden(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $this->assertNull($user->calendar_token);

        $this->post('/calendar/feed/token')
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->calendar_token);
        $this->assertEquals(64, strlen($user->calendar_token));
    }
}

