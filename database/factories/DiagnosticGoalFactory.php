<?php

namespace Database\Factories;

use App\Models\DiagnosticGoal;
use App\Models\DiagnosticStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosticGoalFactory extends Factory
{
    protected $model = DiagnosticGoal::class;

    public function definition(): array
    {
        return [
            'diagnostic_stage_id' => DiagnosticStage::factory(),
            'code'                => strtoupper($this->faker->lexify('??-##-?')),
            'description'         => $this->faker->sentence(),
            'sort_order'          => $this->faker->numberBetween(1, 50),
        ];
    }
}

