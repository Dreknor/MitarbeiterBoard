<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Models\RecurringProcedure;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringProcedureFactory extends Factory
{
    protected $model = RecurringProcedure::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->sentence(3),
            'procedure_id'    => Procedure::factory()->vorlage(),
            'faelligkeit_typ' => 'datum',
            'month'           => $this->faker->numberBetween(1, 12),
            'wochen'          => null,
            'ferien'          => null,
        ];
    }

    /** Typ: Datum (jeden Monat am 1.) */
    public function datum(int $month = null): static
    {
        return $this->state(fn () => [
            'faelligkeit_typ' => 'datum',
            'month'           => $month ?? $this->faker->numberBetween(1, 12),
            'wochen'          => null,
            'ferien'          => null,
        ]);
    }

    /** Typ: Wochen vor Ferien */
    public function vorFerien(string $ferien = 'Sommerferien', int $wochen = 2): static
    {
        return $this->state(fn () => [
            'faelligkeit_typ' => 'vor_ferien',
            'month'           => null,
            'wochen'          => $wochen,
            'ferien'          => $ferien,
        ]);
    }

    /** Typ: Wochen nach Ferien */
    public function nachFerien(string $ferien = 'Sommerferien', int $wochen = 1): static
    {
        return $this->state(fn () => [
            'faelligkeit_typ' => 'nach_ferien',
            'month'           => null,
            'wochen'          => $wochen,
            'ferien'          => $ferien,
        ]);
    }
}

