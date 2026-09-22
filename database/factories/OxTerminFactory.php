<?php

namespace Database\Factories;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use Illuminate\Database\Eloquent\Factories\Factory;

class OxTerminFactory extends Factory
{
    protected $model = OxTermin::class;

    public function definition(): array
    {
        $beginn = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $ende   = (clone $beginn)->modify('+1 hour');

        return [
            'ox_calendar_id' => OxCalendar::factory(),
            'ox_uid'         => $this->faker->uuid() . '@ox.example.com',
            'ox_etag'        => '"' . $this->faker->md5() . '"',
            'ox_href'        => '/caldav/events/' . $this->faker->uuid() . '.ics',
            'titel'          => $this->faker->randomElement([
                'Gesamtkonferenz', 'Fachkonferenz', 'Elternsprechtag',
                'Fortbildung', 'Dienstberatung', 'AG Treffen',
            ]),
            'beschreibung'   => $this->faker->optional()->paragraph(),
            'ort'            => $this->faker->optional()->randomElement(['Aula', 'Raum 201', 'Lehrerzimmer', 'Online']),
            'beginn'         => $beginn,
            'ende'           => $ende,
            'timezone'       => 'Europe/Berlin',
            'ganztaegig'     => false,
            'rrule'          => null,
            'exdates'        => null,
            'status'         => 'CONFIRMED',
            'erstellt_von'   => null,
            'raw_ical'       => null,
        ];
    }

    public function ganztaegig(): static
    {
        return $this->state(function () {
            $date = $this->faker->dateTimeBetween('+1 day', '+30 days');
            return [
                'beginn'     => $date->format('Y-m-d') . ' 00:00:00',
                'ende'       => $date->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
                'ganztaegig' => true,
            ];
        });
    }

    public function wiederkehrend(): static
    {
        return $this->state(fn () => [
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO;COUNT=10',
        ]);
    }

    public function vergangen(): static
    {
        return $this->state(function () {
            $beginn = $this->faker->dateTimeBetween('-30 days', '-1 day');
            $ende   = (clone $beginn)->modify('+1 hour');
            return ['beginn' => $beginn, 'ende' => $ende];
        });
    }
}

