<?php

namespace Database\Factories;

use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosticAssessmentFactory extends Factory
{
    protected $model = DiagnosticAssessment::class;

    public function definition(): array
    {
        return [
            'diagnostic_session_id' => DiagnosticSession::factory(),
            'diagnostic_goal_id'    => DiagnosticGoal::factory(),
            'rating'                => $this->faker->randomElement(['white', 'gray', 'dark_gray']),
            'is_current_goal'       => false,
            'saved_at'              => now(),
        ];
    }

    /** Bewertung als aktuelles Ziel markieren */
    public function asCurrentGoal(): static
    {
        return $this->state(fn () => ['is_current_goal' => true]);
    }
}

