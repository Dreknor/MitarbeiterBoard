<?php

namespace Database\Factories\personal;

use App\Models\Group;
use App\Models\User;
use App\Models\personal\HortPlanung;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class HortPlanungFactory extends Factory
{
    protected $model = HortPlanung::class;

    public function definition(): array
    {
        $start = Carbon::now()->subMonths(3)->startOfMonth();

        return [
            'name'          => 'Planung ' . fake()->year() . ' ' . fake()->word(),
            'beschreibung'  => fake()->optional()->sentence(),
            'department_id' => Group::factory()->asDepartment(),
            'start_monat'   => $start,
            'end_monat'     => $start->copy()->addMonths(11),
            'typ'           => 'planung',
            'aktiv'         => false,
            'kopiert_von_id' => null,
            'created_by'    => User::factory(),
        ];
    }

    /** Aktive Planung */
    public function aktiv(): static
    {
        return $this->state(fn() => ['aktiv' => true]);
    }

    /** Planungs-Typ "Rückblick" */
    public function rueckblick(): static
    {
        return $this->state(fn() => ['typ' => 'rueckblick']);
    }

    /** Planung mit festem Zeitraum (n Monate ab $start) */
    public function mitZeitraum(Carbon $start, int $monate = 12): static
    {
        return $this->state(fn() => [
            'start_monat' => $start->copy()->startOfMonth(),
            'end_monat'   => $start->copy()->startOfMonth()->addMonths($monate - 1),
        ]);
    }
}

