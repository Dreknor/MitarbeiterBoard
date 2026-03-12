<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'title'       => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(),
            'status'      => 'open',
            'user_id'     => User::factory(),
            'assigned_to' => null,
            'priority'    => $this->faker->randomElement(['low', 'medium', 'high']),
            'category_id' => TicketCategory::factory(),
        ];
    }

    /** Offenes Ticket */
    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }

    /** Geschlossenes Ticket */
    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }
}

