<?php

namespace Database\Factories;

use App\Models\DiagnosticArea;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DiagnosticAreaFactory extends Factory
{
    protected $model = DiagnosticArea::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);
        return [
            'name'        => $name,
            'slug'        => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 99999),
            'description' => $this->faker->sentence(),
            'sort_order'  => $this->faker->numberBetween(1, 100),
            'active'      => true,
        ];
    }

    /** Inaktiver Bereich */
    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}


