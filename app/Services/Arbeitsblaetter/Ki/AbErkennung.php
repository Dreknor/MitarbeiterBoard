<?php

namespace App\Services\Arbeitsblaetter\Ki;

/** Ergebnis einer Erkennung: normalisierte Struktur, Seitenzahl, geschätzte Kosten. */
class AbErkennung
{
    public function __construct(
        public readonly array $struktur,
        public readonly int $seiten,
        public readonly int $kostenCent,
        public readonly string $anbieter,
        public readonly string $modell,
    ) {
    }
}
