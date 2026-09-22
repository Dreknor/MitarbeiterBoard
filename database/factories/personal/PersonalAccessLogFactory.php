<?php

namespace Database\Factories\personal;

use App\Models\personal\PersonalAccessLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalAccessLogFactory extends Factory
{
    protected $model = PersonalAccessLog::class;

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'action'        => 'view',
            'resource_type' => 'App\\Models\\User',
            'resource_id'   => null,
            'route'         => 'personal.employes.show',
            'ip_address'    => $this->faker->ipv4(),
            'metadata'      => null,
        ];
    }
}

