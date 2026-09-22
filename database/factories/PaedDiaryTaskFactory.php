<?php

namespace Database\Factories;

use App\Models\Klasse;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaedDiaryTaskFactory extends Factory
{
    protected $model = PaedDiaryTask::class;

    public function definition(): array
    {
        return [
            'klasse_id'   => Klasse::factory(),
            'schueler_id' => Schueler::factory(),
            'title'       => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'due_date'    => $this->faker->dateTimeBetween('now', '+4 weeks')->format('Y-m-d'),
            'status'      => 'open',
            'highlighted' => false,
            'created_by'  => User::factory(),
            'closed_at'   => null,
        ];
    }

    /**
     * Aufgabe einem Schüler zuordnen.
     */
    public function forSchueler(Schueler $schueler): static
    {
        return $this->state(fn () => ['schueler_id' => $schueler->id]);
    }

    /**
     * Aufgabe als abgeschlossen markieren.
     */
    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed', 'closed_at' => now()]);
    }

    /**
     * Aufgabe hervorheben.
     */
    public function highlighted(): static
    {
        return $this->state(fn () => ['highlighted' => true]);
    }
}


