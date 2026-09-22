<?php

namespace Database\Factories;

use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Step;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureStepFactory extends Factory
{
    protected $model = Procedure_Step::class;

    public function definition(): array
    {
        return [
            'name'         => $this->faker->sentence(3),
            'description'  => $this->faker->optional()->paragraph(),
            'procedure_id' => Procedure::factory(),
            'position_id'  => Positions::factory(),
            'parent'       => null,
            'durationDays' => $this->faker->numberBetween(1, 14),
            'endDate'      => null,
            'done'         => false,
        ];
    }

    /**
     * Schritt als erledigt markieren.
     */
    public function erledigt(): static
    {
        return $this->state(fn () => [
            'done'    => true,
            'endDate' => now()->subDay(),
        ]);
    }

    /**
     * Schritt mit gesetztem endDate, noch offen.
     */
    public function offen(): static
    {
        return $this->state(fn () => [
            'done'    => false,
            'endDate' => now()->addDays(7),
        ]);
    }

    /**
     * Schritt ist überfällig.
     */
    public function ueberfaellig(): static
    {
        return $this->state(fn () => [
            'done'    => false,
            'endDate' => now()->subDays(3),
        ]);
    }
}

