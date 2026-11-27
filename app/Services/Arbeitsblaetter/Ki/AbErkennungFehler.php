<?php

namespace App\Services\Arbeitsblaetter\Ki;

use RuntimeException;

/** Erkennung fehlgeschlagen. `$wiederholbar` = vorübergehender Fehler (Netz, 429, 5xx). */
class AbErkennungFehler extends RuntimeException
{
    public function __construct(string $meldung, public readonly bool $wiederholbar = false)
    {
        parent::__construct($meldung);
    }
}
