<?php

namespace Database\Factories;

use App\Models\Absence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsenceFactory extends Factory
{
    protected $model = Absence::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 month');
        $end   = (clone $start)->modify('+' . $this->faker->numberBetween(0, 3) . ' days');

        return [
            'users_id'            => User::factory(),
            'creator_id'          => User::factory(),
            'reason'              => 'Urlaub',
            'start'               => $start->format('Y-m-d'),
            'end'                 => $end->format('Y-m-d'),
            'before'              => null,
            'showVertretungsplan' => false,
            'sick_note_required'  => false,
            'sick_note_date'      => null,
        ];
    }

    /** Krankmeldung */
    public function krankheit(): static
    {
        return $this->state(fn () => [
            'reason'             => 'Krankheit',
            'sick_note_required' => true,
        ]);
    }

    /** Urlaub */
    public function urlaub(): static
    {
        return $this->state(fn () => ['reason' => 'Urlaub']);
    }

    /** Im Vertretungsplan anzeigen */
    public function showInVertretungsplan(): static
    {
        return $this->state(fn () => ['showVertretungsplan' => true]);
    }
}

