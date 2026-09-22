<?php

namespace Tests\Unit\Migrations;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\personal\Employment;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmploymentFieldsMigrationTest extends TestCase
{
    /** @test */
    public function migration_adds_employment_type_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'employment_type'));
    }

    /** @test */
    public function migration_adds_contract_type_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'contract_type'));
    }

    /** @test */
    public function migration_adds_status_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'status'));
    }

    /** @test */
    public function migration_adds_status_reason_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'status_reason'));
    }

    /** @test */
    public function migration_adds_termination_reason_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'termination_reason'));
    }

    /** @test */
    public function migration_adds_probation_end_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'probation_end'));
    }

    /** @test */
    public function migration_adds_notice_period_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'notice_period'));
    }

    /** @test */
    public function migration_adds_salary_group_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'salary_group'));
    }

    /** @test */
    public function migration_adds_salary_level_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'salary_level'));
    }

    /** @test */
    public function migration_adds_is_amendment_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'is_amendment'));
    }

    /** @test */
    public function migration_adds_amendment_description_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'amendment_description'));
    }

    /** @test */
    public function migration_adds_is_internal_transfer_field(): void
    {
        $this->assertTrue(Schema::hasColumn('employments', 'is_internal_transfer'));
    }

    /** @test */
    public function new_employment_has_default_status_aktiv(): void
    {
        $employment = Employment::factory()->create();
        $this->assertEquals(EmploymentStatus::Aktiv, $employment->status);
    }

    /** @test */
    public function employment_casts_employment_type_to_enum(): void
    {
        $employment = Employment::factory()->create([
            'employment_type' => 'lehrer',
        ]);
        $this->assertInstanceOf(EmploymentType::class, $employment->employment_type);
        $this->assertEquals(EmploymentType::Lehrer, $employment->employment_type);
    }

    /** @test */
    public function employment_casts_contract_type_to_enum(): void
    {
        $employment = Employment::factory()->create([
            'contract_type' => 'befristet',
            'end'           => now()->addYear(),
        ]);
        $this->assertInstanceOf(ContractType::class, $employment->contract_type);
        $this->assertTrue($employment->contract_type->isBefristet());
    }

    /** @test */
    public function employment_casts_status_to_enum(): void
    {
        $employment = Employment::factory()->create([
            'status' => 'ruhend',
        ]);
        $this->assertInstanceOf(EmploymentStatus::class, $employment->status);
        $this->assertFalse($employment->status->isActive());
    }
}

