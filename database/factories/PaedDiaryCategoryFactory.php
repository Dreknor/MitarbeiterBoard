<?php

namespace Database\Factories;

use App\Models\PaedDiaryCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaedDiaryCategoryFactory extends Factory
{
    protected $model = PaedDiaryCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Verhalten', 'Lernentwicklung', 'Sozialverhalten',
                'Elterngespräch', 'Förderplan', 'Allgemein',
            ]),
            'user_id' => null, // global per default
        ];
    }

    /**
     * Persönliche Kategorie eines bestimmten Users.
     */
    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}

