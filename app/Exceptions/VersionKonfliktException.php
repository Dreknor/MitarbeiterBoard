<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Der Datensatz wurde zwischenzeitlich von jemand anderem gespeichert (optimistische Sperre).
 * Controller antworten darauf mit 409 und dem aktuellen Stand.
 */
class VersionKonfliktException extends RuntimeException
{
}
