<?php

namespace App\Events\Personal;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wird gefeuert wenn ein Prozess-Schritt abgeschlossen wird.
 * Implementierung der Listener folgt in späteren Phasen.
 */
class ProcedureStepCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $procedureId,
        public readonly int $stepId,
        public readonly int $userId
    ) {}
}

