<?php

namespace Database\Factories\personal;

use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RosterEventsFactory extends Factory
{
    protected $model = RosterEvents::class;

    public function definition(): array
    {
        $date = now()->startOfWeek()->addDays($this->faker->numberBetween(0, 4));

        return [
            'roster_id'  => Roster::factory(),
            'employe_id' => User::factory(),
            'date'       => $date->format('Y-m-d'),
            'start'      => '08:00:00',
            'end'        => '16:00:00',
            'event'      => $this->faker->words(2, true),
        ];
    }
}

