<?php

namespace Tests\Unit\Migrations;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OxCalendarTablesTest extends TestCase
{

    /** @test */
    public function ox_calendars_tabelle_existiert_mit_allen_spalten(): void
    {
        $this->assertTrue(Schema::hasTable('ox_calendars'));
        $this->assertTrue(Schema::hasColumns('ox_calendars', [
            'id', 'ox_calendar_id', 'name', 'farbe', 'beschreibung',
            'sichtbar', 'schreibbar', 'sync_token', 'letzte_synchronisation',
            'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    /** @test */
    public function ox_termine_tabelle_existiert_mit_allen_spalten(): void
    {
        $this->assertTrue(Schema::hasTable('ox_termine'));
        $this->assertTrue(Schema::hasColumns('ox_termine', [
            'id', 'ox_calendar_id', 'ox_uid', 'ox_etag', 'ox_href',
            'titel', 'beschreibung', 'ort', 'beginn', 'ende', 'timezone',
            'ganztaegig', 'rrule', 'exdates', 'status', 'erstellt_von',
            'raw_ical', 'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    /** @test */
    public function ox_termin_teilnehmer_tabelle_existiert(): void
    {
        $this->assertTrue(Schema::hasTable('ox_termin_teilnehmer'));
        $this->assertTrue(Schema::hasColumns('ox_termin_teilnehmer', [
            'id', 'ox_termin_id', 'email', 'name', 'status',
        ]));
    }

    /** @test */
    public function ox_calendar_group_pivot_tabelle_existiert(): void
    {
        $this->assertTrue(Schema::hasTable('ox_calendar_group'));
        $this->assertTrue(Schema::hasColumns('ox_calendar_group', [
            'id', 'ox_calendar_id', 'group_id', 'schreibbar',
        ]));
    }

    /** @test */
    public function ox_sync_log_tabelle_existiert(): void
    {
        $this->assertTrue(Schema::hasTable('ox_sync_log'));
        $this->assertTrue(Schema::hasColumns('ox_sync_log', [
            'id', 'ox_calendar_id', 'aktion', 'details', 'user_id', 'ip_adresse',
        ]));
        // Kein SoftDeletes auf ox_sync_log
        $this->assertFalse(Schema::hasColumn('ox_sync_log', 'deleted_at'));
    }

    /** @test */
    public function kalender_permissions_existieren(): void
    {
        $this->assertTrue(
            Permission::where('name', 'view calendar')->exists()
        );
        $this->assertTrue(
            Permission::where('name', 'create calendar events')->exists()
        );
        $this->assertTrue(
            Permission::where('name', 'edit calendar events')->exists()
        );
        $this->assertTrue(
            Permission::where('name', 'manage calendar')->exists()
        );
    }
}

