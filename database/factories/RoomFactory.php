<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name'               => $this->faker->words(2, true),
            'room_number'        => (string) $this->faker->unique()->numberBetween(100, 999),
            'indiware_shortname' => strtoupper($this->faker->lexify('???')),
            'bookable'           => true,
            'feed_token'         => null,
            'feed_expires_at'    => null,
        ];
    }

    /** Raum als nicht buchbar markieren */
    public function notBookable(): static
    {
        return $this->state(fn () => ['bookable' => false]);
    }
}

