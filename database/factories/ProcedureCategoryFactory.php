<?php

namespace Database\Factories;

use App\Models\Procedure_Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureCategoryFactory extends Factory
{
    protected $model = Procedure_Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
        ];
    }
}

