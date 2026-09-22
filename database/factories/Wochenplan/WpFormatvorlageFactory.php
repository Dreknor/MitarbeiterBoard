<?php

namespace Database\Factories\Wochenplan;

use App\Models\User;
use App\Models\Wochenplan\WpFormatvorlage;
use Illuminate\Database\Eloquent\Factories\Factory;

class WpFormatvorlageFactory extends Factory
{
    protected $model = WpFormatvorlage::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(2, true),
            'beschreibung'  => $this->faker->sentence(),
            'schriftgroesse'=> 'normal',
            'schriftart'    => 'Arial',
            'layout_config' => [
                'papier'  => ['groesse' => 'A4', 'ausrichtung' => 'portrait'],
                'spalten' => [],
            ],
            'blade_template'=> 'wochenplan.pdf.standard',
            'is_default'    => false,
            'created_by'    => User::factory(),
        ];
    }

    /** Als Standard-Formatvorlage */
    public function asDefault(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}

