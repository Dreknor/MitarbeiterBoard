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
