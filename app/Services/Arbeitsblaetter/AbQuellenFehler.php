<?php

namespace App\Services\Arbeitsblaetter;

use RuntimeException;

/** Eingangsdatei kann nicht verarbeitet werden – die Meldung ist für die Nutzer:in gedacht. */
class AbQuellenFehler extends RuntimeException
{
}
