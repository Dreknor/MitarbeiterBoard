<?php

namespace Tests\Unit\Services\Personal;

use App\Models\personal\Employment;
use App\Models\User;
use App\Services\Personal\Zeit\ArbeitszeitService;
use App\Support\Feiertage;
use Carbon\Carbon;
use Tests\TestCase;

class ArbeitszeitServiceTest extends TestCase
{
    public function test_feiertage_sachsen_werden_lokal_berechnet(): void
    {
        $tage = Feiertage::fuerJahr(2026, 'SN')->pluck('title', 'date');

        $this->assertSame('Ostermontag', $tage['2026-04-06']);
        $this->assertSame('Buß- und Bettag', $tage['2026-11-18']);
        $this->assertSame('Reformationstag', $tage['2026-10-31']);
        $this->assertArrayNotHasKey('2026-06-04', $tage->all(), 'Fronleichnam ist in Sachsen kein landesweiter Feiertag');
    }

    public function test_heiligabend_und_silvester_zaehlen_standardmaessig_als_feiertag(): void
    {
        $this->settingSetzen('zeitwirtschaft_stichtag', '2020-01-01');

        $tage = Feiertage::fuerJahr(2026, 'SN')->pluck('title', 'date');

        $this->assertSame('Heiligabend', $tage['2026-12-24']);
        $this->assertSame('Silvester', $tage['2026-12-31']);
        $this->assertSame('Silvester', is_holiday(Carbon::parse('2026-12-31'))['title']);

        $user = User::factory()->create();
        $this->assertSame(0, app(ArbeitszeitService::class)->arbeitstageZwischen($user, Carbon::parse('2026-12-24'), Carbon::parse('2026-12-24')));
    }

    public function test_heiligabend_und_silvester_koennen_als_arbeitstag_gewertet_werden(): void
    {
        $this->settingSetzen('zeitwirtschaft_stichtag', '2020-01-01');
        $this->settingSetzen('heiligabend_feiertag', '0');

        $tage = Feiertage::fuerJahr(2026, 'SN')->pluck('title', 'date');
        $this->assertArrayNotHasKey('2026-12-24', $tage->all());
        $this->assertSame('Silvester', $tage['2026-12-31']);
        $this->assertNull(is_holiday(Carbon::parse('2026-12-24')));

        $this->settingSetzen('silvester_feiertag', '0');
        $this->assertNull(is_holiday(Carbon::parse('2026-12-31')));
    }

    public function test_heiligabend_und_silvester_vor_dem_stichtag_bleiben_arbeitstage(): void
    {
        $this->settingSetzen('zeitwirtschaft_stichtag', '2026-10-01');

        $this->assertNull(is_holiday(Carbon::parse('2025-12-24')));
        $this->assertNull(is_holiday(Carbon::parse('2025-12-31')));
        $this->assertSame('Heiligabend', is_holiday(Carbon::parse('2026-12-24'))['title']);
    }

    private function settingSetzen(string $key, string $wert): void
    {
        \App\Models\Setting::updateOrCreate(['setting' => $key], ['value' => $wert, 'module' => 'Test', 'setting_name' => $key, 'type' => 'string']);
        \Illuminate\Support\Facades\Cache::forget('setting_'.$key);
    }

    public function test_vollzeit_mo_bis_fr_ergibt_acht_stunden_soll(): void
    {
        $user = User::factory()->create();
        Employment::factory()->create(['employe_id' => $user->id, 'hours' => 40, 'start' => '2026-01-01']);

        $service = app(ArbeitszeitService::class);

        $this->assertEquals(8 * 3600, $service->sollSekunden($user, Carbon::parse('2026-09-28')));   // Montag
        $this->assertEquals(0, $service->sollSekunden($user, Carbon::parse('2026-10-03')));          // Samstag + Feiertag
        $this->assertEquals(0, $service->sollSekunden($user, Carbon::parse('2026-11-18')));          // Buß- und Bettag
    }

    public function test_teilzeit_mit_drei_arbeitstagen_verteilt_soll_nur_auf_diese_tage(): void
    {
        $user = User::factory()->create();
        Employment::factory()->create([
            'employe_id' => $user->id, 'hours' => 24, 'start' => '2026-01-01', 'workdays' => [1, 2, 3],
        ]);

        $service = app(ArbeitszeitService::class);

        $this->assertEquals(8 * 3600, $service->sollSekunden($user, Carbon::parse('2026-09-28')), 'Montag: 24 h / 3 Tage');
        $this->assertEquals(0, $service->sollSekunden($user, Carbon::parse('2026-10-01')), 'Donnerstag ist kein Arbeitstag');
        $this->assertSame(3, $service->arbeitstageZwischen($user, Carbon::parse('2026-09-28'), Carbon::parse('2026-10-04')));
    }

    public function test_ohne_vertrag_gilt_montag_bis_freitag(): void
    {
        $user = User::factory()->create();

        $this->assertSame(5, app(ArbeitszeitService::class)->arbeitstageZwischen($user, Carbon::parse('2026-09-21'), Carbon::parse('2026-09-27')));
    }
}
