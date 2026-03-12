<?php

namespace Database\Factories\personal;

use App\Models\personal\Holiday;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+2 months');
        $end   = (clone $start)->modify('+' . $this->faker->numberBetween(1, 5) . ' days');

        return [
            'employe_id'  => User::factory(),
            'start_date'  => $start->format('Y-m-d'),
            'end_date'    => $end->format('Y-m-d'),
            'approved'    => false,
            'rejected'    => false,
            'approved_by' => null,
            'approved_at' => null,
            'days'        => 1,
        ];
    }

    /** Genehmigter Urlaub */
    public function approved(): static
    {
        return $this->state(fn () => [
            'approved'    => true,
            'rejected'    => false,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /** Abgelehnter Urlaub */
    public function rejected(): static
    {
        return $this->state(fn () => [
            'approved' => false,
            'rejected' => true,
        ]);
    }

    /** Ausstehender Urlaub (Standard) */
    public function pending(): static
    {
        return $this->state(fn () => [
            'approved' => false,
            'rejected' => false,
        ]);
    }
}

