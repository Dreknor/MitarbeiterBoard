<?php

namespace Tests\Feature;

use App\Models\OxSyncLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature-Tests für
 */
class CalendarSettingsTest extends TestCase
{
    // =========================================================================
    // Settings-Migration
    // =========================================================================

    public function test_Settings_Migration_erstellt_5_Kalender_Settings(): void
    {
        $count = DB::table('settings')
            ->where('module', 'Kalender')
            ->count();

        $this->assertSame(5, $count);
    }

    public function test_Sync_Intervall_Default_ist_15(): void
    {
        $value = Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_sync_interval')
            ->value('value');

        $this->assertSame('15', $value);
    }

    public function test_Standard_Ansicht_Default_ist_timeGridWeek(): void
    {
        $value = Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_default_ansicht')
            ->value('value');

        $this->assertSame('timeGridWeek', $value);
    }

    // =========================================================================
    // Sync-Log-Bereinigung
    // =========================================================================

    public function test_Sync_Log_Bereinigung_loescht_alte_Eintraege(): void
    {
        // Log von vor 100 Tagen
        OxSyncLog::factory()->create([
            'created_at' => now()->subDays(100),
        ]);
        // Log von gestern
        OxSyncLog::factory()->create([
            'created_at' => now()->subDay(),
        ]);

        // Bereinigung ausführen (Default: 90 Tage)
        $aufbewahrungTage = 90;
        $deleted = OxSyncLog::where('created_at', '<', now()->subDays($aufbewahrungTage))->delete();

        $this->assertSame(1, $deleted);
        $this->assertSame(1, OxSyncLog::count());
    }

    public function test_Sync_Log_Bereinigung_respektiert_konfigurierte_Aufbewahrungsfrist(): void
    {
        // Aufbewahrungsfrist auf 30 Tage setzen
        Setting::updateOrCreate(
            ['module' => 'Kalender', 'setting' => 'calendar_log_aufbewahrung_tage'],
            [
                'setting_name' => 'Log-Aufbewahrung (Tage)',
                'type'         => 'number',
                'value'        => '30',
            ]
        );

        OxSyncLog::factory()->create(['created_at' => now()->subDays(35)]);
        OxSyncLog::factory()->create(['created_at' => now()->subDays(25)]);

        $aufbewahrungTage = (int) Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_log_aufbewahrung_tage')
            ->value('value');

        $deleted = OxSyncLog::where('created_at', '<', now()->subDays($aufbewahrungTage))->delete();

        $this->assertSame(1, $deleted);
        $this->assertSame(1, OxSyncLog::count());
    }

    // =========================================================================
    // Sync-enabled-Setting
    // =========================================================================

    public function test_Sync_Enabled_Default_ist_1(): void
    {
        $value = Setting::where('module', 'Kalender')
            ->where('setting', 'calendar_sync_enabled')
            ->value('value');

        $this->assertSame('1', $value);
    }
}

