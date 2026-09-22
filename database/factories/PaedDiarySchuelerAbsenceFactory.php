<?php

namespace Database\Factories;

use App\Models\Klasse;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaedDiarySchuelerAbsenceFactory extends Factory
{
    protected $model = PaedDiarySchuelerAbsence::class;

    public function definition(): array
    {
        return [
            'schueler_id' => Schueler::factory(),
            'klasse_id'   => Klasse::factory(),
            'datum'       => $this->faker->date(),
            'marked_by'   => User::factory(),
        ];
    }
}

