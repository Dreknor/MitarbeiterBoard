<?php

namespace Tests\Feature\Personal;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentStatusReason;
use App\Enums\TerminationReason;
use App\Models\personal\Employment;
use App\Models\personal\SchoolType;
use App\Models\personal\TeacherDetail;
use App\Models\User;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    /** @test */
    public function contracts_index_requires_permission(): void
    {
        $user   = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('personal.contracts.index', $target->id))
            ->assertStatus(403);
    }

    /** @test */
    public function employment_status_workflow_aktiv_to_ruhend(): void
    {
        $employment = Employment::factory()->create(['status' => 'aktiv']);

        $employment->setRuhend(EmploymentStatusReason::Elternzeit);

        $this->assertEquals(EmploymentStatus::Ruhend, $employment->fresh()->status);
        $this->assertEquals(EmploymentStatusReason::Elternzeit, $employment->fresh()->status_reason);
    }

    /** @test */
    public function ruhend_can_be_set_back_to_aktiv(): void
    {
        $employment = Employment::factory()->create(['status' => 'ruhend']);

        $employment->setAktiv();

        $this->assertEquals(EmploymentStatus::Aktiv, $employment->fresh()->status);
    }

    /** @test */
    public function cannot_set_ruhend_from_beendet(): void
    {
        $employment = Employment::factory()->create(['status' => 'beendet']);

        $this->expectException(\LogicException::class);
        $employment->setRuhend(EmploymentStatusReason::Elternzeit);
    }

    /** @test */
    public function cannot_set_beendet_twice(): void
    {
        $employment = Employment::factory()->create(['status' => 'beendet']);

        $this->expectException(\LogicException::class);
        $employment->setBeendet(TerminationReason::Befristungsablauf);
    }

    /** @test */
    public function scope_active_filters_by_status(): void
    {
        $active  = Employment::factory()->create(['status' => 'aktiv']);
        $ruhend  = Employment::factory()->create(['status' => 'ruhend']);
        $beendet = Employment::factory()->create(['status' => 'beendet']);

        $activeIds = Employment::active()->pluck('id');

        $this->assertContains($active->id, $activeIds);
        $this->assertNotContains($ruhend->id, $activeIds);
        $this->assertNotContains($beendet->id, $activeIds);
    }

    /** @test */
    public function effective_hours_are_calculated_correctly(): void
    {
        $schoolType = SchoolType::factory()->create(['default_deputat' => 28]);
        $employment = Employment::factory()->create(['hours' => 14]); // 50% Stelle

        $detail = TeacherDetail::factory()->create([
            'employment_id'      => $employment->id,
            'school_type_id'     => $schoolType->id,
            'deputat_hours'      => 28,
            'reduction_hours'    => 2,
            'anrechnungsstunden' => 1,
        ]);

        // (14/28 × 28) - 2 - 1 = 14 - 2 - 1 = 11
        $this->assertEquals(11.0, $detail->effective_hours);
    }

    /** @test */
    public function salary_fields_not_visible_without_permission(): void
    {
        $user   = $this->actingAsWithPermission('view contracts', 'view personal_data:all');
        $target = User::factory()->create();
        Employment::factory()->create([
            'employe_id'   => $target->id,
            'salary_group' => 'E11',
            'salary_level' => 'Stufe 3',
            'status'       => 'aktiv',
        ]);

        $response = $this->get(route('personal.contracts.index', $target->id));

        $response->assertStatus(200);
        $response->assertDontSee('Tarifgruppe');
        $response->assertDontSee('E11');
    }
}

