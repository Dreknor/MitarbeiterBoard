<?php

namespace Database\Factories;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Karte auf einem Ergebnisboard – ohne Angabe im ersten Bereich des Boards.
 */
class ErgebnisKarteFactory extends Factory
{
    protected $model = ErgebnisKarte::class;

    public function definition(): array
    {
        return [
            'board_id' => Ergebnisboard::factory(),
            'bereich' => fn (array $a) => Ergebnisboard::find($a['board_id'])?->bereiche[0]['schluessel'] ?? 'ideen',
            'titel' => $this->faker->sentence(4),
            'zusatz' => null,
            'etikett' => null,
            'fokus' => false,
            'breit' => false,
            'sort' => fn (array $a) => (int) ErgebnisKarte::query()->where('board_id', $a['board_id'])->max('sort') + 1,
            'faellig_am' => null,
            'task_id' => null,
        ];
    }
}
