<?php

namespace Database\Factories\Wochenplan;

use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use Illuminate\Database\Eloquent\Factories\Factory;

class WpAufgabeFactory extends Factory
{
    protected $model = WpAufgabe::class;

    public function definition(): array
    {
        return [
            'wp_plan_fach_id' => WpPlanFach::factory(),
            'aufgabe'         => $this->faker->sentence(5),
            'dauer'           => $this->faker->numberBetween(10, 60),
            'sort_order'      => $this->faker->numberBetween(1, 20),
            'synced_from_id'  => null,
        ];
    }
}

