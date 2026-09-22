<?php

namespace Database\Factories;

use App\Models\GradingSystem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GradingSystemFactory extends Factory
{
    protected $model = GradingSystem::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        return [
            'name'        => $name,
            'slug'        => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'description' => $this->faker->sentence(),
            'active'      => true,
        ];
    }

    /** Inaktives Bewertungssystem */
    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}

