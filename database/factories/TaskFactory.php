<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'task'         => $this->faker->sentence(4),
            'date'         => now()->addDays($this->faker->numberBetween(1, 14)),
            'theme_id'     => null,
            'completed'    => false,
            // Polymorphic morph columns – ohne diese führt Task zu Fehlern
            'taskable_type'=> null,
            'taskable_id'  => null,
        ];
    }

    /** Abgeschlossene Aufgabe */
    public function completed(): static
    {
        return $this->state(fn () => ['completed' => true]);
    }
}

