<?php

namespace Database\Factories;

use App\Models\Klasse;
use App\Models\User;
use App\Models\Vertretung;
use Illuminate\Database\Eloquent\Factories\Factory;

class VertretungFactory extends Factory
{
    protected $model = Vertretung::class;

    public function definition(): array
    {
        return [
            'date'         => now()->addDays($this->faker->numberBetween(0, 7)),
            'klassen_id'   => Klasse::factory(),
            'users_id'     => User::factory(),
            'stunde'       => $this->faker->numberBetween(1, 8),
            'comment'      => null,
            'altFach'      => $this->faker->word(),
            'neuFach'      => $this->faker->word(),
            'Doppelstunde' => false,
            'type'         => 'Vertretung',
            'akt_id'       => null,
        ];
    }
}

