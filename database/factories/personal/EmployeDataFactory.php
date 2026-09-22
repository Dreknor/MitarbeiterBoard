<?php

namespace Database\Factories\personal;

use App\Models\personal\EmployeData;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeDataFactory extends Factory
{
    protected $model = EmployeData::class;

    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'familienname'          => $this->faker->lastName(),
            'geburtsname'           => null,
            'vorname'               => $this->faker->firstName(),
            'geburtstag'            => $this->faker->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'geschlecht'            => $this->faker->randomElement(['m', 'w', 'd']),
            'sozialversicherungsnummer' => $this->faker->numerify('##########'),
            'geburtsort'            => $this->faker->city(),
            'staatsangehoerigkeit'  => 'deutsch',
            'schwerbehindert'       => false,
            'caldav_events'         => false,
            'caldav_working_time'   => false,
            'mail_timesheet'        => false,
        ];
    }
}

