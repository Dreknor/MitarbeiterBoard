<?php

namespace Database\Factories\personal;

use App\Models\personal\Roster;
use App\Models\personal\WorkingTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkingTimeFactory extends Factory
{
    protected $model = WorkingTime::class;

    public function definition(): array
    {
        $date  = now()->startOfWeek()->addDays($this->faker->numberBetween(0, 4));
        $start = '08:00:00';
        $end   = '16:00:00';

        return [
            'employe_id' => User::factory(),
            'roster_id'  => Roster::factory(),
            'date'       => $date->format('Y-m-d'),
            'start'      => $start,
            'end'        => $end,
            'function'   => null,
        ];
    }
}

