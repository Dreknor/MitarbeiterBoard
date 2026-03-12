<?php

namespace Database\Factories\personal;

use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmploymentFactory extends Factory
{
    protected $model = Employment::class;

    public function definition(): array
    {
        return [
            'employe_id'    => User::factory(),
            'department_id' => Group::factory()->asDepartment(),
            'hour_type_id'  => HourType::factory(),
            'start'         => now()->subYear()->startOfYear(),
            'end'           => null,
            'hours'         => 40,
            'comment'       => null,
        ];
    }

    /** Aktive Anstellung (kein Enddatum) */
    public function active(): static
    {
        return $this->state(fn () => [
            'start' => now()->subYear()->startOfYear(),
            'end'   => null,
        ]);
    }

    /** Vollzeitstelle (100 %) */
    public function fullTime(): static
    {
        return $this->state(fn () => ['hours' => 40]);
    }

    /** Teilzeitstelle mit angegebenen Prozent */
    public function partTime(int $percent = 50): static
    {
        return $this->state(fn () => ['hours' => intval(40 * $percent / 100)]);
    }
}

