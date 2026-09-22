<?php

namespace Database\Factories;

use App\Models\Klasse;
use Illuminate\Database\Eloquent\Factories\Factory;

class KlasseFactory extends Factory
{
    protected $model = Klasse::class;

    public function definition(): array
    {
        $stufe = $this->faker->numberBetween(1, 12);
        return [
            'name'              => $stufe . $this->faker->randomLetter(),
            'kuerzel'           => strtoupper($this->faker->lexify('??')),
            'color'             => $this->faker->hexColor(),
            'show_vertretungen' => false,
        ];
    }

    /** Klasse mit öffentlichem Vertretungsplan */
    public function withPublicVertretungen(): static
    {
        return $this->state(fn () => ['show_vertretungen' => true]);
    }
}

