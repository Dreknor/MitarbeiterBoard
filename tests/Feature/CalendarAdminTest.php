<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-Tests für CalendarAdminController (TODO 19).
 */
class CalendarAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ox-calendar.url'      => 'https://ox.example.com/caldav',
            'ox-calendar.username' => 'testuser',
            'ox-calendar.password' => 'testpass',
            'ox-calendar.enabled'  => true,
        ]);

        Http::fake(['*' => Http::response(
            '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"></d:multistatus>',
            207
        )]);
    }

    // =========================================================================
    // Admin-Seite: Berechtigungen
    // =========================================================================

    public function test_Admin_Seite_nur_fuer_User_mit_manage_calendar(): void
    {
        // Ohne manage calendar → 403
        $this->actingAsWithPermission('view calendar');
        $this->get(route('calendar.admin'))->assertForbidden();

        // Mit manage calendar → 200
        $this->actingAsWithPermission('view calendar', 'manage calendar');
        $this->get(route('calendar.admin'))->assertOk();
    }

    // =========================================================================
    // Kalender hinzufügen
    // =========================================================================

    public function test_Kalender_kann_hinzugefuegt_werden(): void
    {
        $this->actingAsWithPermission('manage calendar');

        $this->post(route('calendar.admin.store'), [
            'ox_calendar_id' => '/caldav/schulkalender',
            'name'           => 'Schulkalender',
            'farbe'          => '#FF5733',
            'sichtbar'       => true,
            'schreibbar'     => false,
        ])->assertRedirect();

        $this->assertTrue(OxCalendar::where('name', 'Schulkalender')->exists());
    }

    // =========================================================================
    // Kalender bearbeiten
    // =========================================================================

    public function test_Kalender_kann_bearbeitet_werden(): void
    {
        $this->actingAsWithPermission('manage calendar');
        $calendar = OxCalendar::factory()->create(['name' => 'Alt']);

        $this->put(route('calendar.admin.update', $calendar), [
            'ox_calendar_id' => $calendar->ox_calendar_id,
            'name'           => 'Neu',
            'farbe'          => '#0000FF',
            'sichtbar'       => true,
            'schreibbar'     => true,
        ])->assertRedirect();

        $calendar->refresh();
        $this->assertSame('Neu', $calendar->name);
        $this->assertSame('#0000FF', $calendar->farbe);
    }

    // =========================================================================
    // Kalender löschen (SoftDelete)
    // =========================================================================

    public function test_Kalender_kann_geloescht_werden_SoftDelete(): void
    {
        $this->actingAsWithPermission('manage calendar');
        $calendar = OxCalendar::factory()->create();

        $this->delete(route('calendar.admin.destroy', $calendar))->assertRedirect();

        // Nicht mehr im normalen Query
        $this->assertNull(OxCalendar::find($calendar->id));
        // Aber per withTrashed noch vorhanden
        $this->assertNotNull(OxCalendar::withTrashed()->find($calendar->id));
    }

    // =========================================================================
    // Gruppen-Zuordnung
    // =========================================================================

    public function test_Gruppen_Zuordnung_kann_aktualisiert_werden(): void
    {
        $this->actingAsWithPermission('manage calendar');
        $calendar = OxCalendar::factory()->create();
        $group1   = Group::factory()->create();
        $group2   = Group::factory()->create();

        $this->post(route('calendar.admin.gruppen', $calendar), [
            'gruppen' => [
                ['group_id' => $group1->id, 'schreibbar' => false],
                ['group_id' => $group2->id, 'schreibbar' => true],
            ],
        ])->assertRedirect();

        $calendar->load('groups');
        $this->assertCount(2, $calendar->groups);
        $this->assertTrue(
            (bool) $calendar->groups->firstWhere('id', $group2->id)->pivot->schreibbar
        );
    }

    // =========================================================================
    // Manueller Sync
    // =========================================================================

    public function test_Manueller_Sync_kann_ausgeloest_werden(): void
    {
        $this->actingAsWithPermission('manage calendar');
        OxCalendar::factory()->create(['sichtbar' => true]);

        // HTTP-Fake für CalDAV-Anfragen (PROPFIND, REPORT etc.)
        Http::fake(['*' => Http::response(
            '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"></d:multistatus>',
            207
        )]);

        $this->post(route('calendar.admin.sync'))
            ->assertRedirect()
            ->assertSessionHas('type');
    }

    // =========================================================================
    // Sync-Logs
    // =========================================================================

    public function test_Sync_Logs_sind_abrufbar(): void
    {
        $this->actingAsWithPermission('manage calendar');
        OxSyncLog::factory()->count(5)->create();

        $this->get(route('calendar.admin.logs'))->assertOk();
    }

    public function test_Sync_Logs_sind_filterbar_nach_Aktion(): void
    {
        $this->actingAsWithPermission('manage calendar');
        OxSyncLog::factory()->fehler()->count(3)->create();
        OxSyncLog::factory()->create(['aktion' => 'sync_complete']);

        $this->get(route('calendar.admin.logs', ['aktion' => 'error']))
            ->assertOk();
    }

    // =========================================================================
    // Validierung
    // =========================================================================

    public function test_Farbcode_wird_validiert(): void
    {
        $this->actingAsWithPermission('manage calendar');

        $this->post(route('calendar.admin.store'), [
            'ox_calendar_id' => '/caldav/test',
            'name'           => 'Test',
            'farbe'          => 'ungültig',
        ])->assertSessionHasErrors('farbe');
    }
}

