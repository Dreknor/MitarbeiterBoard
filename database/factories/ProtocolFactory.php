<?php

namespace Database\Factories;

use App\Models\Protocol;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProtocolFactory extends Factory
{
    protected $model = Protocol::class;

    public function definition(): array
    {
        return [
            'theme_id'   => Theme::factory(),
            'creator_id' => User::factory(),
            'protocol'   => $this->faker->paragraph(),
        ];
    }
}

