<?php

namespace Database\Factories;

use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaedDiaryColumnFactory extends Factory
{
    protected $model = PaedDiaryColumn::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Verhalten', 'Mitarbeit', 'Hausaufgaben', 'Soziales', 'Bemerkung',
        ]);

        return [
            'klasse_id'        => Klasse::factory(),
            'name'             => $name,
            'slug'             => \Illuminate\Support\Str::slug($name . '-' . $this->faker->unique()->numerify('###')),
            'type'             => $this->faker->randomElement(['text', 'checkbox', 'select']),
            'sort_order'       => $this->faker->numberBetween(1, 20),
            'active'           => true,
            'deactivated_from' => null,
            'category'         => null,
        ];
    }

    /**
     * Spalte mit Kategorie.
     */
    public function withCategory(string $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }

    /**
     * Inaktive Spalte (ab einem Datum deaktiviert).
     */
    public function deactivatedFrom(\DateTimeInterface|string $date): static
    {
        return $this->state(fn () => ['deactivated_from' => $date, 'active' => false]);
    }
}

