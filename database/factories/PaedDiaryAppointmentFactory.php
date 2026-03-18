<?php

namespace Database\Factories;

use App\Models\PaedDiaryAppointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaedDiaryAppointmentFactory extends Factory
{
    protected $model = PaedDiaryAppointment::class;

    public function definition(): array
    {
        return [
            'user_id'            => User::factory(),
            'title'              => $this->faker->sentence(3),
            'description'        => $this->faker->optional()->paragraph(),
            'start_date'         => $this->faker->dateTimeBetween('now', '+4 weeks')->format('Y-m-d'),
            'start_time'         => $this->faker->time('H:i'),
            'end_time'           => $this->faker->time('H:i'),
            'is_recurring'       => false,
            'recurring_type'     => null,
            'recurring_interval' => 1,  // NOT NULL mit default(1)
            'recurring_end_date' => null,
            'is_paused'          => false,
        ];
    }

    /**
     * Wiederkehrender Termin.
     */
    public function recurring(string $type = 'weekly', int $interval = 1): static
    {
        return $this->state(fn () => [
            'is_recurring'       => true,
            'recurring_type'     => $type,
            'recurring_interval' => $interval,
            'recurring_end_date' => now()->addMonths(3)->format('Y-m-d'),
        ]);
    }
}


