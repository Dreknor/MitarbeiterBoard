<?php

namespace Database\Factories\personal;

use App\Models\personal\SchoolType;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolTypeFactory extends Factory
{
    protected $model = SchoolType::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->randomElement(['Grundschule', 'Oberschule', 'Gymnasium', 'Förderschule']),
            'default_deputat' => $this->faker->randomElement([28.0, 26.0, 25.5]),
            'stundentafel'    => null,
            'is_active'       => true,
        ];
    }
}

