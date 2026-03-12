<?php

namespace Database\Factories\personal;

use App\Models\personal\HourType;
use Illuminate\Database\Eloquent\Factories\Factory;

class HourTypeFactory extends Factory
{
    protected $model = HourType::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(2, true),
            'start'         => now()->subYears(2)->startOfYear(),
            'end'           => null,
            'fulltimehours' => 40,
            'minutes'       => 2400,
        ];
    }
}

