<?php

namespace Database\Factories\personal;

use App\Models\personal\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'name'                  => $this->faker->words(3, true),
            'category'              => $this->faker->randomElement(['vertrag', 'zeugnis', 'bescheinigung', 'sonstiges']),
            'requires_expiry'       => $this->faker->boolean(30),
            'default_reminder_days' => $this->faker->optional()->numberBetween(14, 90),
            'nextcloud_subfolder'   => $this->faker->slug,
            'is_active'             => true,
        ];
    }
}

