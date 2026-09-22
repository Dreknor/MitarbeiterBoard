<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        return [
            'group_id'    => Group::factory()->asMeetingGroup(),
            'date'        => now()->addDays($this->faker->numberBetween(1, 30))->toDateString(),
            'start_time'  => '09:00',
            'end_time'    => '10:00',
            'title'       => $this->faker->sentence(3),
            'description' => null,
            'cancelled'   => false,
            'cancelled_at'=> null,
            'cancelled_by'=> null,
        ];
    }

    /** Vergangenes Meeting */
    public function past(): static
    {
        return $this->state(fn () => [
            'date' => now()->subDays($this->faker->numberBetween(1, 60))->toDateString(),
        ]);
    }

    /** Zukünftiges Meeting */
    public function upcoming(): static
    {
        return $this->state(fn () => [
            'date' => now()->addDays($this->faker->numberBetween(1, 30))->toDateString(),
        ]);
    }

    /** Abgesagtes Meeting */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'cancelled'    => true,
            'cancelled_at' => now(),
        ]);
    }
}



