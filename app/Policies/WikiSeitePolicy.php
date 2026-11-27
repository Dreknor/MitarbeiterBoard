<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WikiSeite;
use App\Services\Wiki\WikiZugriff;

/**
 * Wiki: `view wiki` liest, `edit wiki` legt an/bearbeitet/verschiebt/stellt Fassungen wieder her,
 * `manage wiki` schützt, schränkt ein, sortiert, löscht (Papierkorb) und pflegt Tags.
 * Eingeschränkte Seiten (WikiZugriff) gelten für Lesen und Schreiben; geschützte und Systemseiten
 * darf nur `manage wiki` bearbeiten.
 */
class WikiSeitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view wiki');
    }

    public function view(User $user, WikiSeite $seite): bool
    {
        return $user->can('view wiki') && WikiZugriff::darfSehen($user, $seite);
    }

    public function create(User $user): bool
    {
        return $user->can('view wiki') && $user->can('edit wiki');
    }

    public function update(User $user, WikiSeite $seite): bool
    {
        if ($seite->trashed() || ! $this->view($user, $seite) || ! $user->can('edit wiki')) {
            return false;
        }

        return ! $seite->istGeschuetzt() || $user->can('manage wiki');
    }

    public function manage(User $user, WikiSeite $seite): bool
    {
        return $user->can('manage wiki') && $this->view($user, $seite);
    }

    public function delete(User $user, WikiSeite $seite): bool
    {
        return ! $seite->trashed() && $this->manage($user, $seite);
    }

    public function restore(User $user, WikiSeite $seite): bool
    {
        return $seite->trashed() && $user->can('manage wiki');
    }
}
