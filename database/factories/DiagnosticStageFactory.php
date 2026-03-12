<?php

namespace Database\Factories;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosticStageFactory extends Factory
{
    protected $model = DiagnosticStage::class;

    public function definition(): array
    {
        return [
            'diagnostic_area_id' => DiagnosticArea::factory(),
            'name'               => $this->faker->words(2, true),
            'code'               => strtoupper($this->faker->lexify('??-##')),
            'goal_description'   => $this->faker->sentence(),
            'sort_order'         => $this->faker->numberBetween(1, 50),
        ];
    }
}

