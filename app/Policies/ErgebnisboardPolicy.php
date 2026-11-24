<?php

namespace App\Policies;

use App\Models\Ergebnisboard;
use App\Models\User;
use App\Services\Ergebnisboards\ErgebnisboardService;
use App\Support\ErgebnisboardVorlagen;

/**
 * Zugriff auf Ergebnisboards. Die Rechte (lesen/schreiben/verwalten über Person, Gruppe,
 * Rolle, Ersteller, Sitzung) ermittelt ErgebnisboardService::rechtFuer().
 */
class ErgebnisboardPolicy
{
    public function __construct(private ErgebnisboardService $service)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can(ErgebnisboardVorlagen::PERMISSION_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(ErgebnisboardVorlagen::PERMISSION_CREATE) || $user->can(ErgebnisboardVorlagen::PERMISSION_MANAGE);
    }

    public function view(User $user, Ergebnisboard $board): bool
    {
        return $this->service->darf($board, $user, 'lesen');
    }

    /** Karten, Felder, Kopf, Anhänge – nicht bei archivierten Boards. */
    public function update(User $user, Ergebnisboard $board): bool
    {
        return ! $board->istArchiviert() && $this->service->darf($board, $user, 'schreiben');
    }

    /** Zugriffe ändern, archivieren, löschen. */
    public function manage(User $user, Ergebnisboard $board): bool
    {
        return $this->service->darf($board, $user, 'verwalten');
    }

    public function duplicate(User $user, Ergebnisboard $board): bool
    {
        return $this->create($user) && $this->view($user, $board);
    }

    public function manageTemplates(User $user): bool
    {
        return $user->can(ErgebnisboardVorlagen::PERMISSION_MANAGE);
    }
}
