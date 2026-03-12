<?php

namespace Database\Factories;

use App\Models\Klasse;
use App\Models\Schueler;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchuelerFactory extends Factory
{
    protected $model = Schueler::class;

    public function definition(): array
    {
        return [
            'vorname'     => $this->faker->firstName(),
            'nachname'    => $this->faker->lastName(),
            'geburtsdatum'=> $this->faker->dateTimeBetween('-18 years', '-5 years')->format('Y-m-d'),
            'klasse_id'   => Klasse::factory(),
            'import_key'  => $this->faker->unique()->numerify('SCH####'),
        ];
    }
}

