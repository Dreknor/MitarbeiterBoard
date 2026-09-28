<?php

namespace Database\Factories;

use App\Models\DiagnosticDevelopmentGoal;
use App\Models\Schueler;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosticDevelopmentGoalFactory extends Factory
{
    protected $model = DiagnosticDevelopmentGoal::class;

    public function definition(): array
    {
        return [
            'schueler_id' => Schueler::factory(),
            'title' => $this->faker->sentence(6),
            'target_date' => now()->addMonths(2)->toDateString(),
            'status' => DiagnosticDevelopmentGoal::STATUS_IN_PROGRESS,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => [
            'status' => DiagnosticDevelopmentGoal::STATUS_ACHIEVED,
            'completed_at' => now()->toDateString(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => DiagnosticDevelopmentGoal::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);
    }
}
