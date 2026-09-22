<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Theme;
use App\Models\Type;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        return [
            'theme'       => $this->faker->sentence(4),
            'information' => $this->faker->paragraph(),
            'goal'        => null,
            'duration'    => $this->faker->numberBetween(5, 60),
            'memory'      => false,
            'completed'   => false,
            'creator_id'  => User::factory(),
            'group_id'    => Group::factory(),
            'type_id'     => \App\Models\Type::factory(),
            'date'        => now()->addDays($this->faker->numberBetween(1, 30))->toDateString(),
            'assigned_to' => null,
        ];
    }

    /** Archiviertes Thema */
    public function archived(): static
    {
        return $this->state(fn () => ['memory' => true]);
    }

    /** Geschlossenes Thema */
    public function closed(): static
    {
        return $this->state(fn () => ['completed' => now()]);
    }
}


