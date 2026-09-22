<?php

namespace Database\Factories\personal;

use App\Models\personal\HortPlanung;
use App\Models\personal\HortPlanungMonat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class HortPlanungMonatFactory extends Factory
{
    protected $model = HortPlanungMonat::class;

    public function definition(): array
    {
        return [
            'hort_planung_id' => HortPlanung::factory(),
            'monat'           => Carbon::now()->startOfMonth(),
            'kinderanzahl'    => fake()->numberBetween(80, 120),
            'vollzeitstunden' => 40.00,
            'notiz'           => null,
        ];
    }

    /** Monat in der Vergangenheit */
    public function vergangen(int $monate = 1): static
    {
        return $this->state(fn() => [
            'monat' => Carbon::now()->subMonths($monate)->startOfMonth(),
        ]);
    }

    /** Monat in der Zukunft */
    public function zukunft(int $monate = 1): static
    {
        return $this->state(fn() => [
            'monat' => Carbon::now()->addMonths($monate)->startOfMonth(),
        ]);
    }

    /** Monat mit festem Datum */
    public function mitMonat(Carbon $datum): static
    {
        return $this->state(fn() => [
            'monat' => $datum->copy()->startOfMonth(),
        ]);
    }
}

