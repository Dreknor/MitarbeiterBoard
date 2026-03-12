<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id'   => User::factory(),
            'comment'   => $this->faker->paragraph(),
            'internal'  => false,
        ];
    }

    /** Interner Kommentar */
    public function internal(): static
    {
        return $this->state(fn () => ['internal' => true]);
    }
}

