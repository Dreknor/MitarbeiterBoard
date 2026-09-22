<?php

namespace Database\Factories;

use App\Models\OxCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

class OxCalendarFactory extends Factory
{
    protected $model = OxCalendar::class;

    public function definition(): array
    {
        return [
            'ox_calendar_id'          => '/caldav/' . $this->faker->uuid(),
            'name'                    => $this->faker->randomElement([
                'Schulkalender', 'Lehrerkalender', 'Fortbildungen',
                'Fachschaft Deutsch', 'Verwaltung',
            ]),
            'farbe'                   => $this->faker->hexColor(),
            'beschreibung'            => $this->faker->optional()->sentence(),
            'sichtbar'                => true,
            'schreibbar'              => false,
            'sync_token'              => null,
            'letzte_synchronisation'  => null,
        ];
    }

    public function schreibbar(): static
    {
        return $this->state(fn () => ['schreibbar' => true]);
    }

    public function unsichtbar(): static
    {
        return $this->state(fn () => ['sichtbar' => false]);
    }

    public function synchronisiert(): static
    {
        return $this->state(fn () => [
            'sync_token'             => 'sync-token-' . fake()->uuid(),
            'letzte_synchronisation' => now(),
        ]);
    }
}

