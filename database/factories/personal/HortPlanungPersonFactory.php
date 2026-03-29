<?php

namespace Database\Factories\personal;

use App\Models\User;
use App\Models\personal\HortPlanungMonat;
use App\Models\personal\HortPlanungPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

class HortPlanungPersonFactory extends Factory
{
    protected $model = HortPlanungPerson::class;

    public function definition(): array
    {
        $gesamt = fake()->randomFloat(2, 20, 40);

        return [
            'hort_planung_monat_id' => HortPlanungMonat::factory(),
            'user_id'               => User::factory(),
            'stunden_gesamt'        => $gesamt,
            'stunden_stadt'         => round($gesamt * 0.85, 2),
            'stunden_vertrag'       => $gesamt,
            'stunden_ist'           => null,
            'kommentar'             => null,
        ];
    }

    /** Vollzeitkraft (40h) */
    public function vollzeit(): static
    {
        return $this->state(fn() => [
            'stunden_gesamt'  => 40.00,
            'stunden_stadt'   => 34.00,
            'stunden_vertrag' => 40.00,
        ]);
    }

    /** Person ohne Stunden (Platzhalter) */
    public function leer(): static
    {
        return $this->state(fn() => [
            'stunden_gesamt'  => null,
            'stunden_stadt'   => null,
            'stunden_vertrag' => null,
        ]);
    }

    /** Mit erfassten Ist-Stunden */
    public function mitIstStunden(float $istStunden = 38.5): static
    {
        return $this->state(fn() => ['stunden_ist' => $istStunden]);
    }
}

