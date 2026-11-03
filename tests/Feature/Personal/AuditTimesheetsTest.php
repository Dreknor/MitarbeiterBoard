<?php

namespace Tests\Feature\Personal;

use App\Enums\EmploymentStatus;
use App\Models\personal\Employment;
use App\Models\User;
use App\Services\Personal\TimeValidationService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * personal:audit-timesheets muss Mitarbeiter nach Vertragszeitraum auswählen, nicht nach
 * heutigem Vertragsstatus – abgelaufene Verträge stehen bereits auf "beendet".
 */
class AuditTimesheetsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pruefengine(): Mockery\MockInterface
    {
        $mock = Mockery::mock(TimeValidationService::class);
        $this->app->instance(TimeValidationService::class, $mock);

        return $mock;
    }

    public function test_beendeter_vertrag_wird_fuer_vergangenen_monat_geprueft(): void
    {
        Carbon::setTestNow('2026-10-14');
        $user = User::factory()->create();
        Employment::factory()->create([
            'employe_id' => $user->id,
            'start'      => '2025-01-01',
            'end'        => '2026-03-15', // endet mitten im Monat
            'status'     => EmploymentStatus::Beendet,
        ]);

        $this->pruefengine()->shouldReceive('runForEmployee')->once()
            ->withArgs(fn ($employe, $monat) => $employe->is($user) && $monat->format('Y-m') === '2026-03')
            ->andReturn(collect());

        $this->artisan('personal:audit-timesheets', ['--employe' => $user->id, '--month' => '2026-03'])
            ->assertSuccessful();
    }

    public function test_beendeter_vertrag_wird_im_zeitraum_geprueft(): void
    {
        Carbon::setTestNow('2026-10-14');
        $user = User::factory()->create();
        Employment::factory()->create([
            'employe_id' => $user->id,
            'start'      => '2025-01-01',
            'end'        => '2026-02-28',
            'status'     => EmploymentStatus::Beendet,
        ]);

        $this->pruefengine()->shouldReceive('runForEmployeeRange')->once()->andReturn(collect());

        $this->artisan('personal:audit-timesheets', ['--employe' => $user->id, '--from' => '2026-01', '--to' => '2026-06'])
            ->assertSuccessful();
    }

    public function test_monat_ausserhalb_des_vertrags_wird_uebersprungen(): void
    {
        Carbon::setTestNow('2026-10-14');
        $user = User::factory()->create();
        Employment::factory()->create([
            'employe_id' => $user->id,
            'start'      => '2025-01-01',
            'end'        => '2026-02-28',
            'status'     => EmploymentStatus::Beendet,
        ]);

        $this->pruefengine()->shouldNotReceive('runForEmployee');

        $this->artisan('personal:audit-timesheets', ['--employe' => $user->id, '--month' => '2026-03'])
            ->assertSuccessful();
    }

    public function test_ruhender_vertrag_wird_uebersprungen(): void
    {
        Carbon::setTestNow('2026-10-14');
        $user = User::factory()->create();
        Employment::factory()->create([
            'employe_id' => $user->id,
            'start'      => '2025-01-01',
            'end'        => null,
            'status'     => EmploymentStatus::Ruhend,
        ]);

        $this->pruefengine()->shouldNotReceive('runForEmployee');

        $this->artisan('personal:audit-timesheets', ['--employe' => $user->id, '--month' => '2026-09'])
            ->assertSuccessful();
    }
}
