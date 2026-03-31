<?php

namespace App\Events\Personal;

use App\Models\personal\Employment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wird gefeuert wenn eine Anstellung beendet wird.
 * Implementierung der Listener folgt in späteren Phasen.
 */
class EmploymentTerminated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Employment $employment
    ) {}
}

