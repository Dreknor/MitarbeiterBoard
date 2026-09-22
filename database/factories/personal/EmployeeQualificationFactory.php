<?php

namespace Database\Factories\personal;

use App\Enums\QualificationStatus;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\QualificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeQualificationFactory extends Factory
{
    protected $model = EmployeeQualification::class;

    public function definition(): array
    {
        $acquired    = $this->faker->dateTimeBetween('-3 years', '-1 month');
        $validMonths = $this->faker->numberBetween(12, 60);
        $expiry      = $this->faker->optional(0.7)->dateTimeBetween('+1 month', '+3 years');

        return [
            'employe_id'            => User::factory(),
            'qualification_type_id' => QualificationType::factory(),
            'acquired_date'         => $acquired,
            'expiry_date'           => $expiry,
            'document_id'           => null,
            'status'                => QualificationStatus::Gueltig,
            'notes'                 => null,
            'verified_by'           => null,
            'verified_at'           => null,
        ];
    }

    public function ablaufend(): static
    {
        return $this->state([
            'expiry_date' => now()->addDays(30),
            'status'      => QualificationStatus::Ablaufend,
        ]);
    }

    public function abgelaufen(): static
    {
        return $this->state([
            'expiry_date' => now()->subDays(10),
            'status'      => QualificationStatus::Abgelaufen,
        ]);
    }
}

