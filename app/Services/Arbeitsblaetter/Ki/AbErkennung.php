<?php

namespace App\Services\Arbeitsblaetter\Ki;

/**
 * Ergebnis einer Erkennung: normalisierte Struktur, Seitenzahl, geschätzte Kosten und
 * Hinweise für die Lehrkraft (z. B. „mit dem Ersatzmodell erkannt“).
 */
class AbErkennung
{
    public function __construct(
        public readonly array $struktur,
        public readonly int $seiten,
        public readonly float $kostenCent,
        public readonly string $anbieter,
        public readonly string $modell,
        public readonly array $hinweise = [],
    ) {
    }
}

