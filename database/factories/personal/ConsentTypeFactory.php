<?php

namespace Database\Factories\personal;

use App\Models\personal\ConsentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsentTypeFactory extends Factory
{
    protected $model = ConsentType::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'legal_basis' => 'Art. 6 Abs. 1 lit. a DSGVO',
            'key'         => $this->faker->unique()->slug(2),
            'is_required' => false,
            'is_active'   => true,
        ];
    }
}

