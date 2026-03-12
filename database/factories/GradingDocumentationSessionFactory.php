<?php

namespace Database\Factories;

use App\Models\GradingDocumentationSession;
use App\Models\GradingSystem;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradingDocumentationSessionFactory extends Factory
{
    protected $model = GradingDocumentationSession::class;

    public function definition(): array
    {
        return [
            'schueler_id'      => Schueler::factory(),
            'grading_system_id'=> GradingSystem::factory(),
            'user_id'          => User::factory(),
            'type'             => 'individual',
            'klasse_id'        => null,
            'group_id'         => null,
            'started_at'       => now(),
            'completed_at'     => null,
        ];
    }

    /** Abgeschlossene Session */
    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }

    /** Gruppen-Session */
    public function asGroup(): static
    {
        return $this->state(fn () => [
            'type'        => 'group',
            'schueler_id' => null,
        ]);
    }
}

