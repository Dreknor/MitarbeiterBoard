<?php

namespace Tests\Unit\Services\Personal;

use App\Enums\QualificationStatus;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\Employment;
use App\Models\personal\QualificationType;
use App\Models\personal\Training;
use App\Models\personal\TrainingParticipant;
use App\Models\User;
use App\Services\Personal\QualificationService;
use Tests\TestCase;

class QualificationServiceTest extends TestCase
{
    protected QualificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QualificationService::class);
    }

    /** @test */
    public function missing_required_checks_all_active_employment_types(): void
    {
        $employe = User::factory()->create();

        // MA ist Lehrer UND regulärer Angestellter
        Employment::factory()->create([
            'employe_id'      => $employe->id,
            'employment_type' => 'lehrer',
            'status'          => 'aktiv',
        ]);
        Employment::factory()->create([
            'employe_id'      => $employe->id,
            'employment_type' => 'regulaer',
            'status'          => 'aktiv',
        ]);

        // Pflichtqualifikation nur für Lehrer
        $lehrerQual = QualificationType::factory()->create([
            'category'   => 'pflicht',
            'applies_to' => json_encode(['lehrer']),
        ]);

        $missing = $this->service->getMissingRequired($employe);

        $this->assertContains($lehrerQual->id, $missing->pluck('id'));
    }

    /** @test */
    public function missing_required_ignores_inactive_employment_types(): void
    {
        $employe = User::factory()->create();

        // Beendete Anstellung als Lehrer
        Employment::factory()->create([
            'employe_id'      => $employe->id,
            'employment_type' => 'lehrer',
            'status'          => 'beendet',
        ]);
        // Nur aktive reguläre Anstellung
        Employment::factory()->create([
            'employe_id'      => $employe->id,
            'employment_type' => 'regulaer',
            'status'          => 'aktiv',
        ]);

        // Pflichtqualifikation nur für Lehrer
        $lehrerQual = QualificationType::factory()->create([
            'category'   => 'pflicht',
            'applies_to' => json_encode(['lehrer']),
        ]);

        $missing = $this->service->getMissingRequired($employe);

        // Lehrer-Qualifikation sollte NICHT fehlen, da keine aktive Lehrer-Anstellung
        $this->assertNotContains($lehrerQual->id, $missing->pluck('id'));
    }

    /** @test */
    public function status_is_ablaufend_within_reminder_days(): void
    {
        $type    = QualificationType::factory()->create(['reminder_days' => 90]);
        $employe = User::factory()->create();
        $qual    = EmployeeQualification::factory()->create([
            'employe_id'            => $employe->id,
            'qualification_type_id' => $type->id,
            'acquired_date'         => now()->subYear(),
            'expiry_date'           => now()->addDays(60), // Innerhalb der 90-Tage-Frist
        ]);

        $this->service->getQualificationStatus($employe);

        $this->assertEquals('ablaufend', $qual->fresh()->status->value);
    }

    /** @test */
    public function status_is_gueltig_outside_reminder_days(): void
    {
        $type    = QualificationType::factory()->create(['reminder_days' => 90]);
        $employe = User::factory()->create();
        $qual    = EmployeeQualification::factory()->create([
            'employe_id'            => $employe->id,
            'qualification_type_id' => $type->id,
            'acquired_date'         => now()->subYear(),
            'expiry_date'           => now()->addDays(120), // Außerhalb der 90-Tage-Frist
        ]);

        $this->service->getQualificationStatus($employe);

        $this->assertEquals('gueltig', $qual->fresh()->status->value);
    }

    /** @test */
    public function status_is_abgelaufen_when_expiry_in_past(): void
    {
        $type    = QualificationType::factory()->create(['reminder_days' => 30]);
        $employe = User::factory()->create();
        $qual    = EmployeeQualification::factory()->create([
            'employe_id'            => $employe->id,
            'qualification_type_id' => $type->id,
            'acquired_date'         => now()->subYears(3),
            'expiry_date'           => now()->subDays(10), // Abgelaufen
        ]);

        $this->service->getQualificationStatus($employe);

        $this->assertEquals('abgelaufen', $qual->fresh()->status->value);
    }

    /** @test */
    public function status_is_gueltig_when_no_expiry_date(): void
    {
        $type    = QualificationType::factory()->create(['validity_months' => null]);
        $employe = User::factory()->create();
        $qual    = EmployeeQualification::factory()->create([
            'employe_id'            => $employe->id,
            'qualification_type_id' => $type->id,
            'acquired_date'         => now()->subYear(),
            'expiry_date'           => null,
        ]);

        $this->service->getQualificationStatus($employe);

        $this->assertEquals('gueltig', $qual->fresh()->status->value);
    }

    /** @test */
    public function renew_from_training_creates_employee_qualification(): void
    {
        $qualType = QualificationType::factory()->create(['validity_months' => 24]);
        $training = Training::factory()->create([
            'qualification_type_id' => $qualType->id,
            'end_date'              => now()->toDateString(),
            'status'                => 'bestaetigt',
        ]);
        $employe     = User::factory()->create();
        $participant = TrainingParticipant::factory()->create([
            'training_id' => $training->id,
            'employe_id'  => $employe->id,
            'status'      => 'bestaetigt',
        ]);

        $this->service->renewFromTraining($participant);

        $this->assertDatabaseHas('pers_employee_qualifications', [
            'employe_id'            => $employe->id,
            'qualification_type_id' => $qualType->id,
            'status'                => 'gueltig',
        ]);
    }

    /** @test */
    public function renew_from_training_calculates_expiry_date(): void
    {
        $qualType = QualificationType::factory()->create(['validity_months' => 12]);
        $training = Training::factory()->create([
            'qualification_type_id' => $qualType->id,
            'end_date'              => now()->toDateString(),
        ]);
        $participant = TrainingParticipant::factory()->create([
            'training_id' => $training->id,
            'status'      => 'bestaetigt',
        ]);

        $this->service->renewFromTraining($participant);

        $qual = EmployeeQualification::where('employe_id', $participant->employe_id)->first();
        $this->assertNotNull($qual);
        $this->assertNotNull($qual->expiry_date);
        $this->assertTrue($qual->expiry_date->isAfter(now()->addMonths(11)));
    }

    /** @test */
    public function renew_from_training_without_qualification_type_does_nothing(): void
    {
        $training = Training::factory()->create(['qualification_type_id' => null]);
        $participant = TrainingParticipant::factory()->create([
            'training_id' => $training->id,
        ]);

        $this->service->renewFromTraining($participant);

        $this->assertDatabaseCount('pers_employee_qualifications', 0);
    }

    /** @test */
    public function qualification_matrix_is_accessible_with_permission(): void
    {
        $this->actingAsWithPermission('view qualifications', 'view personal_data:all');

        $response = $this->get(route('personal.qualifications.matrix'));
        $response->assertStatus(200);
    }

    /** @test */
    public function qualification_matrix_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('personal.qualifications.matrix'))->assertStatus(403);
    }

    /** @test */
    public function qualification_store_calculates_expiry_from_validity_months(): void
    {
        $user    = $this->actingAsWithPermission('manage qualifications', 'view personal_data:all');
        $target  = User::factory()->create();
        $type    = QualificationType::factory()->create(['validity_months' => 24]);

        $this->post(route('personal.qualifications.store', $target->id), [
            'qualification_type_id' => $type->id,
            'acquired_date'         => now()->toDateString(),
        ]);

        $qual = EmployeeQualification::where('employe_id', $target->id)->first();
        $this->assertNotNull($qual);
        $this->assertNotNull($qual->expiry_date);
        $this->assertTrue($qual->expiry_date->isAfter(now()->addMonths(23)));
    }
}

