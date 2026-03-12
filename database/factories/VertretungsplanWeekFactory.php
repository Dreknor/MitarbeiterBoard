<?php

namespace Database\Factories;

use App\Models\VertretungsplanWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

class VertretungsplanWeekFactory extends Factory
{
    protected $model = VertretungsplanWeek::class;

    public function definition(): array
    {
        return [
            'week' => now()->startOfWeek()->addWeeks($this->faker->numberBetween(0, 4)),
            'type' => $this->faker->randomElement(['A', 'B']),
        ];
    }
}

