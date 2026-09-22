<?php

namespace Database\Factories;

use App\Models\Positions;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionsFactory extends Factory
{
    protected $model = Positions::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->jobTitle(),
        ];
    }
}

