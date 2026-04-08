<?php

namespace App\Events\Personal;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wird gefeuert wenn sich der Name eines Mitarbeiters ändert.
 * Triggert Nextcloud-Ordner-Umbenennung.
 * Implementierung der Listener folgt in späteren Phasen.
 */
class EmployeeNameChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $oldName,
        public readonly string $newName
    ) {}
}

