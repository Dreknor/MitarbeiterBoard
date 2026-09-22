<?php

namespace Database\Factories;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticSession;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosticSessionFactory extends Factory
{
    protected $model = DiagnosticSession::class;

    public function definition(): array
    {
        return [
            'schueler_id'        => Schueler::factory(),
            'diagnostic_area_id' => DiagnosticArea::factory(),
            'user_id'            => User::factory(),
            'session_date'       => now()->toDateString(),
            'started_at'         => now(),
            'completed_at'       => null,
            'is_completed'       => false,
            'notes'              => null,
        ];
    }

    /** Abgeschlossene Diagnosesitzung */
    public function completed(): static
    {
        return $this->state(fn () => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}

