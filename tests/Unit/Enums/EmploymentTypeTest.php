<?php

namespace Tests\Unit\Enums;

use App\Enums\EmploymentType;
use Tests\TestCase;

class EmploymentTypeTest extends TestCase
{
    /** @test */
    public function it_has_all_required_cases(): void
    {
        $values = array_column(EmploymentType::cases(), 'value');
        $this->assertContains('regulaer', $values);
        $this->assertContains('lehrer', $values);
        $this->assertContains('praktikant', $values);
        $this->assertContains('ehrenamt', $values);
        $this->assertContains('minijob', $values);
        $this->assertCount(5, $values);
    }

    /** @test */
    public function it_returns_german_label(): void
    {
        $this->assertEquals('Lehrkraft', EmploymentType::Lehrer->label());
        $this->assertEquals('Reguläre Anstellung', EmploymentType::Regulaer->label());
        $this->assertEquals('Praktikant/in', EmploymentType::Praktikant->label());
        $this->assertEquals('Ehrenamt', EmploymentType::Ehrenamt->label());
        $this->assertEquals('Minijob', EmploymentType::Minijob->label());
    }

    /** @test */
    public function it_detects_teacher_type(): void
    {
        $this->assertTrue(EmploymentType::Lehrer->requiresTeacherDetail());
        $this->assertFalse(EmploymentType::Regulaer->requiresTeacherDetail());
        $this->assertFalse(EmploymentType::Praktikant->requiresTeacherDetail());
    }

    /** @test */
    public function it_can_be_created_from_value(): void
    {
        $enum = EmploymentType::from('lehrer');
        $this->assertEquals(EmploymentType::Lehrer, $enum);
    }

    /** @test */
    public function it_throws_on_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        EmploymentType::from('ungueltig');
    }
}

