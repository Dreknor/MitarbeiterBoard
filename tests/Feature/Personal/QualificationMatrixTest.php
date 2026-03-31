<?php

namespace Tests\Feature\Personal;

use App\Enums\QualificationStatus;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\QualificationType;
use App\Models\User;
use App\Services\Personal\QualificationService;
use Tests\TestCase;

class QualificationMatrixTest extends TestCase
{
    /** @test */
    public function matrix_route_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('personal.qualifications.matrix'))->assertStatus(403);
    }

    /** @test */
    public function matrix_route_accessible_with_permission(): void
    {
        $this->actingAsWithPermission('view qualifications', 'view personal_data:all');

        $this->get(route('personal.qualifications.matrix'))->assertStatus(200);
    }

    /** @test */
    public function matrix_service_returns_employees_and_types(): void
    {
        $viewer = $this->actingAsWithPermission('view qualifications', 'view personal_data:all', 'manage qualifications');

        $type = QualificationType::factory()->pflicht()->create();
        $employe = User::factory()->create();
        EmployeeQualification::factory()->create([
            'employe_id'            => $employe->id,
            'qualification_type_id' => $type->id,
            'status'                => QualificationStatus::Gueltig,
        ]);

        $service = app(QualificationService::class);
        $result  = $service->getQualificationMatrix($viewer);

        $this->assertArrayHasKey('employees', $result);
        $this->assertArrayHasKey('types', $result);
        $this->assertTrue($result['types']->contains('id', $type->id));
    }

    /** @test */
    public function get_missing_required_detects_missing_qualification(): void
    {
        $user = $this->actingAsWithPermission('view qualifications', 'view personal_data:all');

        // Pflichtqualifikation ohne applies_to (gilt für alle)
        $type = QualificationType::factory()->pflicht()->create(['applies_to' => null]);

        // Mitarbeiter mit aktiver Anstellung aber ohne Qualifikation
        $employe = User::factory()->create();
        \App\Models\personal\Employment::factory()->create([
            'employe_id' => $employe->id,
            'status'  => 'aktiv',
        ]);

        $service = app(QualificationService::class);
        $missing = $service->getMissingRequired($employe);

        $this->assertTrue($missing->contains('id', $type->id));
    }

    /** @test */
    public function get_missing_required_does_not_flag_fulfilled_qualification(): void
    {
        $user = $this->actingAsWithPermission('view qualifications', 'view personal_data:all');

        $type = QualificationType::factory()->pflicht()->create(['applies_to' => null]);

        $employe = User::factory()->create();
        \App\Models\personal\Employment::factory()->create([
            'employe_id' => $employe->id,
            'status'  => 'aktiv',
        ]);

        // Qualifikation ist gültig
        EmployeeQualification::factory()->create([
            'employe_id'            => $employe->id,
            'qualification_type_id' => $type->id,
            'status'                => QualificationStatus::Gueltig,
        ]);

        $service = app(QualificationService::class);
        $missing = $service->getMissingRequired($employe);

        $this->assertFalse($missing->contains('id', $type->id));
    }

    /** @test */
    public function calculate_status_returns_abgelaufen_for_expired(): void
    {
        $type = QualificationType::factory()->create(['reminder_days' => 30]);
        $qual = EmployeeQualification::factory()->create([
            'qualification_type_id' => $type->id,
            'expiry_date'           => now()->subDays(5),
            'status'                => QualificationStatus::Gueltig,
        ]);

        $service = app(QualificationService::class);
        $this->assertEquals(QualificationStatus::Abgelaufen, $service->calculateStatus($qual));
    }

    /** @test */
    public function calculate_status_returns_ablaufend_within_reminder_period(): void
    {
        $type = QualificationType::factory()->create(['reminder_days' => 60]);
        $qual = EmployeeQualification::factory()->create([
            'qualification_type_id' => $type->id,
            'expiry_date'           => now()->addDays(30),
            'status'                => QualificationStatus::Gueltig,
        ]);

        $service = app(QualificationService::class);
        $this->assertEquals(QualificationStatus::Ablaufend, $service->calculateStatus($qual));
    }
}


