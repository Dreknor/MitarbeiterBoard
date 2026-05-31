<?php

namespace Database\Factories;

use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'category_id' => Procedure_Category::factory(),
            'author_id'   => User::factory(),
            'started_at'  => null,
            'ended_at'    => null,
        ];
    }

    /**
     * Prozess ist noch eine Vorlage (started_at = null).
     */
    public function vorlage(): static
    {
        return $this->state(fn () => [
            'started_at' => null,
            'ended_at'   => null,
        ]);
    }

    /**
     * Prozess wurde gestartet.
     */
    public function gestartet(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subDays(rand(1, 10)),
            'ended_at'   => null,
        ]);
    }

    /**
     * Prozess ist abgeschlossen.
     */
    public function abgeschlossen(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subDays(20),
            'ended_at'   => now()->subDays(2),
        ]);
    }
}

