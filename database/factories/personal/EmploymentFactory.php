<?php

namespace Database\Factories\personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmploymentFactory extends Factory
{
    protected $model = Employment::class;

    public function definition(): array
    {
        return [
            'employe_id'      => User::factory(),
            'department_id'   => Group::factory()->asDepartment(),
            'hour_type_id'    => HourType::factory(),
            'start'           => now()->subYear()->startOfYear(),
            'end'             => null,
            'hours'           => 40,
            'comment'         => null,
            'employment_type' => EmploymentType::Regulaer,
            'contract_type'   => ContractType::Unbefristet,
            'status'          => EmploymentStatus::Aktiv,
        ];
    }

    /** Aktive Anstellung (kein Enddatum) */
    public function active(): static
    {
        return $this->state(fn () => [
            'start'  => now()->subYear()->startOfYear(),
            'end'    => null,
            'status' => EmploymentStatus::Aktiv,
        ]);
    }

    /** Vollzeitstelle (100 %) */
    public function fullTime(): static
    {
        return $this->state(fn () => ['hours' => 40]);
    }

    /** Teilzeitstelle mit angegebenen Prozent */
    public function partTime(int $percent = 50): static
    {
        return $this->state(fn () => ['hours' => intval(40 * $percent / 100)]);
    }

    /** Lehrkraft-Anstellung */
    public function lehrer(): static
    {
        return $this->state(fn () => [
            'employment_type' => EmploymentType::Lehrer,
        ]);
    }

    /** Befristete Anstellung */
    public function befristet(\DateTimeInterface $end = null): static
    {
        return $this->state(fn () => [
            'contract_type' => ContractType::Befristet,
            'end'           => $end ?? now()->addYear(),
        ]);
    }

    /** Beendete Anstellung */
    public function beendet(): static
    {
        return $this->state(fn () => [
            'status' => EmploymentStatus::Beendet,
            'end'    => now()->subMonth(),
        ]);
    }

    /** Ruhende Anstellung */
    public function ruhend(string $reason = 'elternzeit'): static
    {
        return $this->state(fn () => [
            'status'        => EmploymentStatus::Ruhend,
            'status_reason' => $reason,
        ]);
    }
}

