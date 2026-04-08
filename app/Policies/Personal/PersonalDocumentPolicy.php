<?php

namespace App\Policies\Personal;

use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonalDocumentPolicy
{
    use HandlesAuthorization;

    public function view(User $user, $document): bool
    {
        if ($document->employe_id === $user->id) return true;
        return $user->can('view personal_documents')
            && app(PersonalScopeService::class)->canAccess($user, $document->employe);
    }

    public function create(User $user): bool
    {
        return $user->can('create personal_documents');
    }

    public function manage(User $user, $document): bool
    {
        return $user->can('manage personal_documents')
            && app(PersonalScopeService::class)->canAccess($user, $document->employe, 'edit');
    }

    public function delete(User $user, $document): bool
    {
        return $user->can('manage personal_documents')
            && app(PersonalScopeService::class)->canAccess($user, $document->employe, 'edit');
    }
}

