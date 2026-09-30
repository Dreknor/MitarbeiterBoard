<?php

namespace Tests\Feature\Personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\TerminationReason;
use App\Events\Personal\EmploymentCreated;
use App\Events\Personal\EmploymentTerminated;
use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\personal\SchoolType;
use App\Models\User;
use App\Services\Personal\ContractService;
use App\Services\Personal\ContractValidationService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContractWorkflowTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'employment_type' => 'regulaer',
            'contract_type'   => 'unbefristet',
            'department_id'   => Group::factory()->asDepartment()->create()->id,
            'hour_type_id'    => HourType::factory()->create()->id,
            'start'           => now()->toDateString(),
            'hours'           => 20,
            'workdays'        => [1, 2, 3],
        ], $override);
    }

    private function hr(): User
    {
        return $this->actingAsWithPermission('view personal_data', 'view contracts', 'edit contracts', 'view personal_data:all', 'edit personal_data:all');
    }

    /** @test */
    public function storing_a_contract_redirects_to_overview_and_fires_created_event(): void
    {
        Event::fake([EmploymentCreated::class]);
        $this->hr();
        $employe = User::factory()->create();

        $this->post(route('personal.contracts.store', $employe->id), $this->payload())
            ->assertRedirect(route('personal.contracts.index', $employe->id))
            ->assertSessionHas('type', 'success');

        $employment = Employment::where('employe_id', $employe->id)->firstOrFail();
        $this->assertSame(EmploymentStatus::Aktiv, $employment->status);
        Event::assertDispatched(EmploymentCreated::class, fn ($e) => $e->employment->is($employment));
    }

    /** @test */
    public function department_and_hour_type_are_required(): void
    {
        $this->hr();
        $employe = User::factory()->create();

        $this->post(route('personal.contracts.store', $employe->id), $this->payload(['department_id' => '', 'hour_type_id' => '']))
            ->assertSessionHasErrors(['department_id', 'hour_type_id']);
        $this->assertDatabaseCount('employments', 0);
    }

    /** @test */
    public function fixed_term_contract_requires_end_date(): void
    {
        $this->hr();
        $employe = User::factory()->create();

        $this->post(route('personal.contracts.store', $employe->id), $this->payload(['contract_type' => 'befristet']))
            ->assertSessionHasErrors('end');
    }

    /** @test */
    public function salary_fields_are_ignored_without_edit_salary_permission(): void
    {
        $this->hr();
        $employe = User::factory()->create();

        $this->post(route('personal.contracts.store', $employe->id), $this->payload(['salary_group' => 'E13']));

        $this->assertNull(Employment::where('employe_id', $employe->id)->firstOrFail()->salary_group);
    }

    /** @test */
    public function replacing_a_contract_ends_the_old_one_the_day_before_without_offboarding(): void
    {
        Event::fake([EmploymentTerminated::class]);
        $this->hr();
        $employe = User::factory()->create();
        $old = Employment::factory()->create(['employe_id' => $employe->id, 'start' => '2024-01-01']);

        $this->post(route('personal.contracts.store', $employe->id), $this->payload([
            'start' => '2026-09-01',
            'replaced_employment_id' => $old->id,
        ]))->assertSessionHas('type', 'success');

        $old->refresh();
        $this->assertSame(EmploymentStatus::Beendet, $old->status);
        $this->assertSame('2026-08-31', $old->end->toDateString());
        Event::assertNotDispatched(EmploymentTerminated::class);
    }

    /** @test */
    public function editing_a_teacher_contract_persists_teacher_details(): void
    {
        $this->hr();
        $employe = User::factory()->create();
        $school = SchoolType::factory()->create(['default_deputat' => 28]);

        $this->post(route('personal.contracts.store', $employe->id), $this->payload([
            'employment_type' => 'lehrer', 'school_type_id' => $school->id, 'deputat_hours' => 20,
        ]));
        $employment = Employment::where('employe_id', $employe->id)->firstOrFail();

        $this->put(route('personal.contracts.update', $employment->id), $this->payload([
            'employment_type' => 'lehrer', 'school_type_id' => $school->id, 'deputat_hours' => 24,
            'department_id' => $employment->department_id, 'hour_type_id' => $employment->hour_type_id,
        ]))->assertSessionHas('type', 'success');

        $this->assertEquals(24, (float) $employment->fresh()->currentTeacherDetail->deputat_hours);
    }

    /** @test */
    public function overview_renders_for_employee_without_contracts_and_with_teacher_tab(): void
    {
        $this->hr();
        $empty = User::factory()->create();
        $this->get(route('personal.contracts.index', $empty->id))->assertOk();

        $teacher = User::factory()->create();
        Employment::factory()->create(['employe_id' => $teacher->id, 'employment_type' => EmploymentType::Lehrer]);
        $this->get(route('personal.contracts.index', $teacher->id))->assertOk()->assertSee('Lehrer-Details');
    }

    /** @test */
    public function reactivating_a_paused_contract_works(): void
    {
        $this->hr();
        $employment = Employment::factory()->create(['status' => 'ruhend']);

        $this->patch(route('personal.contracts.setAktiv', $employment->id))->assertSessionHas('type', 'success');
        $this->assertSame(EmploymentStatus::Aktiv, $employment->fresh()->status);
    }

    /** @test */
    public function terminating_with_future_date_keeps_contract_active_until_expiry(): void
    {
        $this->hr();
        $employment = Employment::factory()->create(['start' => now()->subYear()]);

        $this->patch(route('personal.contracts.setBeendet', $employment->id), [
            'reason' => TerminationReason::KuendigungAN->value,
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $employment->refresh();
        $this->assertSame(EmploymentStatus::Aktiv, $employment->status);
        $this->assertSame(TerminationReason::KuendigungAN, $employment->termination_reason);
    }

    /** @test */
    public function expired_contracts_are_ended_and_only_final_ones_trigger_offboarding(): void
    {
        Event::fake([EmploymentTerminated::class]);

        $final = Employment::factory()->create([
            'contract_type' => ContractType::Befristet, 'start' => '2025-01-01', 'end' => now()->subDay(),
        ]);
        $employe = User::factory()->create();
        $first = Employment::factory()->create([
            'employe_id' => $employe->id, 'contract_type' => ContractType::Befristet, 'start' => '2025-01-01', 'end' => now()->subDay(),
        ]);
        $follow = Employment::factory()->create(['employe_id' => $employe->id, 'start' => now()->toDateString()]);

        $count = app(ContractService::class)->endExpired();

        $this->assertSame(2, $count);
        $this->assertSame(EmploymentStatus::Beendet, $final->fresh()->status);
        $this->assertSame(EmploymentStatus::Beendet, $first->fresh()->status);
        $this->assertSame(EmploymentStatus::Aktiv, $follow->fresh()->status);
        Event::assertDispatchedTimes(EmploymentTerminated::class, 1);
    }

    /** @test */
    public function befristungskette_counts_only_sachgrundlose_and_uses_verlaengerungen(): void
    {
        $employe = User::factory()->create();
        // 4 aufeinanderfolgende sachgrundlose Verträge (= 3 Verlängerungen), je 6 Monate → 24 Monate: zulässig
        foreach ([0, 6, 12, 18] as $offset) {
            Employment::factory()->create([
                'employe_id' => $employe->id, 'contract_type' => ContractType::Befristet,
                'start' => now()->subYears(3)->addMonths($offset)->startOfMonth(),
                'end' => now()->subYears(3)->addMonths($offset + 6)->startOfMonth()->subDay(),
            ]);
        }
        // Sachgrund-Befristungen zählen nicht mit
        Employment::factory()->create([
            'employe_id' => $employe->id, 'contract_type' => ContractType::BefristetSachgrund,
            'start' => now()->subYears(2), 'end' => now()->subYear(),
        ]);

        $result = app(ContractValidationService::class)->checkBefristungsketten($employe->id);

        $this->assertSame(24, $result['total_months']);
        $this->assertSame(3, $result['verlaengerungen']);
        $this->assertFalse($result['warnung']);
    }

    /** @test */
    public function personalakte_hub_shows_hinweise_and_contract_summary(): void
    {
        $this->hr();
        $employe = User::factory()->create();
        Employment::factory()->create([
            'employe_id' => $employe->id, 'contract_type' => ContractType::Befristet,
            'start' => now()->subYear(), 'end' => now()->addDays(30),
        ]);

        $this->get(route('personal.personalakte.show', $employe->id))
            ->assertOk()
            ->assertSee('Vertrag endet am')
            ->assertSee('Stellenanteil');
    }
}
