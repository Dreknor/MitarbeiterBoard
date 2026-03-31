<?php

namespace App\Policies\Personal;

use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeReviewPolicy
{
    use HandlesAuthorization;

    public function view(User $user, $review): bool
    {
        // Eigenes Gespräch
        if ($review->employe_id === $user->id) return true;
        // Reviewer (Gesprächsführer)
        if ($review->reviewer_id === $user->id) return true;
        // Personalleitung
        return $user->can('view all reviews');
    }

    public function create(User $user): bool
    {
        return $user->can('manage reviews');
    }

    public function update(User $user, $review): bool
    {
        return $user->can('manage reviews')
            && app(PersonalScopeService::class)->canAccess($user, $review->employe, 'edit');
    }

    public function delete(User $user, $review): bool
    {
        return $user->can('manage reviews')
            && app(PersonalScopeService::class)->canAccess($user, $review->employe, 'edit');
    }
}

