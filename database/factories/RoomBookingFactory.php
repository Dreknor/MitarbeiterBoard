<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomBookingFactory extends Factory
{
    protected $model = RoomBooking::class;

    public function definition(): array
    {
        $weekday = $this->faker->numberBetween(1, 5);
        $start   = '09:00';
        $end     = '10:00';

        return [
            'room_id'      => Room::factory(),
            'meeting_id'   => null,
            'users_id'     => User::factory(),
            'name'         => $this->faker->words(2, true),
            'klassen'      => null,
            'lehrer'       => null,
            'weekday'      => $weekday,
            'start'        => $start,
            'end'          => $end,
            'is_recurring' => true,
            'booking_date' => null,
            'source'       => 'manual',
            'source_id'    => null,
            'cancelled'    => false,
            'date'         => null,
            'week'         => null,
        ];
    }

    /** Einmalige Buchung */
    public function oneTime(): static
    {
        return $this->state(fn () => [
            'is_recurring' => false,
            'booking_date' => now()->addDays($this->faker->numberBetween(1, 14)),
        ]);
    }

    /** Stornierte Buchung */
    public function cancelled(): static
    {
        return $this->state(fn () => ['cancelled' => true]);
    }

    /** Indiware-XML-Stundenplan-Buchung */
    public function indiwareXml(): static
    {
        return $this->state(fn () => [
            'source'    => 'indiware_xml',
            'source_id' => 'pl_' . $this->faker->unique()->numberBetween(1, 9999) . '_1_1_1',
            'klassen'   => $this->faker->randomElement(['5a', '6b', '7c, 8a']),
            'lehrer'    => $this->faker->randomElement(['Mül', 'Sch', 'Fis']),
        ]);
    }
}

