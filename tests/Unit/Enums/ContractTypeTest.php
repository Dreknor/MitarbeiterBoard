<?php

namespace Tests\Unit\Enums;

use App\Enums\ContractType;
use Tests\TestCase;

class ContractTypeTest extends TestCase
{
    /** @test */
    public function it_has_all_required_cases(): void
    {
        $values = array_column(ContractType::cases(), 'value');
        $this->assertContains('unbefristet', $values);
        $this->assertContains('befristet', $values);
        $this->assertContains('befristet_sachgrund', $values);
        $this->assertCount(3, $values);
    }

    /** @test */
    public function it_returns_german_label(): void
    {
        $this->assertEquals('Unbefristet', ContractType::Unbefristet->label());
        $this->assertEquals('Befristet (ohne Sachgrund)', ContractType::Befristet->label());
        $this->assertEquals('Befristet (mit Sachgrund)', ContractType::BefristetSachgrund->label());
    }

    /** @test */
    public function it_detects_befristet(): void
    {
        $this->assertTrue(ContractType::Befristet->isBefristet());
        $this->assertTrue(ContractType::BefristetSachgrund->isBefristet());
        $this->assertFalse(ContractType::Unbefristet->isBefristet());
    }

    /** @test */
    public function it_can_be_created_from_value(): void
    {
        $this->assertEquals(ContractType::Unbefristet, ContractType::from('unbefristet'));
    }

    /** @test */
    public function it_throws_on_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        ContractType::from('ungueltig');
    }
}

