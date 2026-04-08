<?php

namespace Database\Factories\personal;

use App\Enums\TrainingStatus;
use App\Models\personal\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingFactory extends Factory
{
    protected $model = Training::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 week', '+6 months');
        $end   = $this->faker->dateTimeBetween($start, date('Y-m-d', strtotime($start->format('Y-m-d') . ' +3 days')));

        return [
            'title'                 => $this->faker->sentence(4),
            'description'           => $this->faker->optional()->paragraph,
            'provider'              => $this->faker->optional()->company,
            'start_date'            => $start,
            'end_date'              => $end,
            'location'              => $this->faker->optional()->city,
            'cost'                  => $this->faker->optional()->randomFloat(2, 50, 500),
            'max_participants'      => $this->faker->optional()->numberBetween(5, 30),
            'qualification_type_id' => null,
            'status'                => TrainingStatus::Geplant,
            'created_by'            => User::factory(),
        ];
    }
}

