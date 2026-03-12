<?php

namespace Database\Factories\Wochenplan;

use App\Models\Wochenplan\WpFach;
use Illuminate\Database\Eloquent\Factories\Factory;

class WpFachFactory extends Factory
{
    protected $model = WpFach::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->unique()->word(),
            'sort_order' => $this->faker->numberBetween(1, 20),
            'is_default' => false,
            'symbol_typ' => 'keine',
            'symbol_wert'=> null,
            'symbol_farbe'=> null,
        ];
    }

    /** Als Standard-Fach markieren */
    public function asDefault(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}

