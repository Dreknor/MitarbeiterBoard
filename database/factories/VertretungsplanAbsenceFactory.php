<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VertretungsplanAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

class VertretungsplanAbsenceFactory extends Factory
{
    protected $model = VertretungsplanAbsence::class;

    public function definition(): array
    {
        $start = now()->addDays($this->faker->numberBetween(0, 7));
        $end   = (clone $start)->addDays($this->faker->numberBetween(0, 3));

        return [
            'user_id'    => User::factory(),
            'reason'     => $this->faker->randomElement(['Krankheit', 'Urlaub', 'Fortbildung']),
            'start_date' => $start,
            'end_date'   => $end,
            'absence_id' => null,
        ];
    }
}

