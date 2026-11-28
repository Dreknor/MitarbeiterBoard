<?php

namespace App\Services\Arbeitsblaetter\Ki;

use RuntimeException;

/**
 * Erkennung fehlgeschlagen. `$wiederholbar` = vorübergehender Fehler (Netz, 429, 5xx),
 * `$status` = HTTP-Status (null = keine Antwort, z. B. Zeitüberschreitung).
 */
class AbErkennungFehler extends RuntimeException
{
    public function __construct(string $meldung, public readonly bool $wiederholbar = false, public readonly ?int $status = null)
    {
        parent::__construct($meldung);
    }
}
