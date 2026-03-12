<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'header'    => $this->faker->sentence(5),
            'text'      => $this->faker->paragraphs(2, true),
            'released'  => null,
        ];
    }

    /** Veröffentlichter Beitrag */
    public function released(): static
    {
        return $this->state(fn () => ['released' => now()]);
    }
}

