<?php

namespace Database\Factories;

use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaedDiaryEntryFactory extends Factory
{
    protected $model = PaedDiaryEntry::class;

    public function definition(): array
    {
        return [
            'klasse_id'    => Klasse::factory(),
            'user_id'      => User::factory(),
            'datum'        => $this->faker->dateTimeBetween('-4 weeks', 'now')->format('Y-m-d'),
            'content'      => $this->faker->sentence(),
            'completed_at' => null,
            'category_id'  => null,
            'dossier_only' => false,
        ];
    }

    /**
     * Eintrag als abgeschlossen markieren.
     */
    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }

    /**
     * Eintrag nur für Dossier.
     */
    public function dossierOnly(): static
    {
        return $this->state(fn () => ['dossier_only' => true]);
    }

    /**
     * Eintrag einer Notizkategorie zuordnen.
     */
    public function withCategory(PaedDiaryCategory $cat): static
    {
        return $this->state(fn () => ['category_id' => $cat->id]);
    }
}

