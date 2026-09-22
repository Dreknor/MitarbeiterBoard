<?php

namespace Database\Factories\personal;

use App\Models\Group;
use App\Models\personal\Roster;
use Illuminate\Database\Eloquent\Factories\Factory;

class RosterFactory extends Factory
{
    protected $model = Roster::class;

    public function definition(): array
    {
        return [
            'department_id' => Group::factory()->asDepartment(),
            'start_date'    => now()->startOfWeek(),
            'type'          => 'normal',
            'comment'       => null,
            'published'     => false,
        ];
    }

    /** Veröffentlichter Dienstplan */
    public function published(): static
    {
        return $this->state(fn () => ['published' => true]);
    }

    /** Dienstplan als Vorlage */
    public function asTemplate(): static
    {
        return $this->state(fn () => ['type' => 'template']);
    }
}

