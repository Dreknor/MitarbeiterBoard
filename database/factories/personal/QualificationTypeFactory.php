<?php

namespace Database\Factories\personal;

use App\Models\personal\QualificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualificationTypeFactory extends Factory
{
    protected $model = QualificationType::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->words(3, true),
            'category'        => $this->faker->randomElement(['pflicht', 'empfohlen', 'freiwillig']),
            'validity_months' => $this->faker->optional()->numberBetween(6, 60),
            'reminder_days'   => $this->faker->numberBetween(14, 90),
            'applies_to'      => null,
            'description'     => $this->faker->optional()->sentence,
            'is_active'       => true,
        ];
    }

    public function pflicht(): static
    {
        return $this->state(['category' => 'pflicht']);
    }
}

