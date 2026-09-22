<?php

namespace App\Policies\Personal;

use App\Enums\EmploymentStatus;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingPolicy
{
    use HandlesAuthorization;

    public function register(User $user, $training): bool
    {
        // Nur aktive Mitarbeiter können sich anmelden
        return $user->employments()
            ->where('status', EmploymentStatus::Aktiv->value)
            ->exists();
    }

    public function view(User $user, $training): bool
    {
        return $user->can('view trainings');
    }

    public function create(User $user): bool
    {
        return $user->can('manage trainings');
    }

    public function update(User $user, $training): bool
    {
        return $user->can('manage trainings');
    }

    public function delete(User $user, $training): bool
    {
        return $user->can('manage trainings');
    }

    public function approve(User $user, $training): bool
    {
        return $user->can('approve trainings');
    }
}

