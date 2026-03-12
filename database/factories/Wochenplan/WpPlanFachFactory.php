<?php

namespace Database\Factories\Wochenplan;

use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use Illuminate\Database\Eloquent\Factories\Factory;

class WpPlanFachFactory extends Factory
{
    protected $model = WpPlanFach::class;

    public function definition(): array
    {
        return [
            'wp_plan_id'  => WpPlan::factory(),
            'wp_fach_id'  => WpFach::factory(),
            'custom_name' => null,
            'sort_order'  => $this->faker->numberBetween(1, 20),
        ];
    }
}

