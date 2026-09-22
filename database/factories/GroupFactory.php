<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(2, true),
            'creator_id'    => User::factory(),
            'needsRoster'   => false,
            'hasWochenplan' => false,
            'hasAllocations'=> false,
            'protected'     => false,
            'stack_themes'  => false,
            'use_meetings'  => false,
            'meeting_weekday' => $this->faker->numberBetween(1, 5),
        ];
    }

    /** Gruppe als Abteilung (für Dienstpläne) */
    public function asDepartment(): static
    {
        return $this->state(fn () => ['needsRoster' => true]);
    }

    /** Gruppe als Dienstberatungs-Gruppe */
    public function asMeetingGroup(): static
    {
        return $this->state(fn () => ['needsRoster' => false, 'use_meetings' => true]);
    }

    /** Gruppe mit Wochenplan-Funktion */
    public function withWochenplan(): static
    {
        return $this->state(fn () => ['hasWochenplan' => true]);
    }
}

