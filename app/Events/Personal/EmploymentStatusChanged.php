<?php

namespace App\Events\Personal;

use App\Models\personal\Employment;
use App\Enums\EmploymentStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wird gefeuert wenn sich der Employment-Status ändert.
 * Implementierung der Listener folgt in späteren Phasen.
 */
class EmploymentStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Employment $employment,
        public readonly EmploymentStatus $oldStatus,
        public readonly EmploymentStatus $newStatus
    ) {}
}

