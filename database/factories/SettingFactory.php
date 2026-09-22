<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'module'       => $this->faker->word(),
            'setting'      => $this->faker->unique()->slug(2, '_'),
            'setting_name' => $this->faker->words(3, true),
            'type'         => $this->faker->randomElement(['text', 'boolean', 'number', 'select']),
            'value'        => $this->faker->word(),
            'description'  => $this->faker->sentence(),
        ];
    }

    /** Setting mit konkretem Schlüssel und Wert */
    public function forKey(string $setting, string $value, string $module = 'general'): static
    {
        return $this->state(fn () => [
            'module'  => $module,
            'setting' => $setting,
            'value'   => $value,
        ]);
    }
}

