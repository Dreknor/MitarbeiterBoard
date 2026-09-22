<?php

namespace Database\Factories\personal;

use App\Enums\ParticipantStatus;
use App\Models\personal\Training;
use App\Models\personal\TrainingParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingParticipantFactory extends Factory
{
    protected $model = TrainingParticipant::class;

    public function definition(): array
    {
        return [
            'training_id'             => Training::factory(),
            'employe_id'              => User::factory(),
            'status'                  => ParticipantStatus::Angemeldet,
            'certificate_document_id' => null,
            'feedback'                => null,
            'approved_by'             => null,
            'approved_at'             => null,
        ];
    }

    public function bestaetigt(): static
    {
        return $this->state([
            'status'      => ParticipantStatus::Bestaetigt,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function teilgenommen(): static
    {
        return $this->state(['status' => ParticipantStatus::Teilgenommen]);
    }
}

