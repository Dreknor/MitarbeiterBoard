<?php

namespace Database\Factories\personal;

use App\Models\personal\Consent;
use App\Models\personal\ConsentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    public function definition(): array
    {
        return [
            'employe_id'      => User::factory(),
            'consent_type_id' => ConsentType::factory(),
            'granted_at'      => now(),
            'revoked_at'      => null,
            'granted_via'     => 'self_service',
        ];
    }
}

