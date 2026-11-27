<?php

namespace App\Services\Arbeitsblaetter\Ki;

/** Ohne KI: Blätter werden manuell im Editor erfasst. */
class KeinErkenner implements AbErkenner
{
    public function konfiguriert(): bool
    {
        return false;
    }

    public function anbieter(): string
    {
        return 'keiner';
    }

    public function modell(): string
    {
        return '';
    }

    public function erkennen(array $seiten, ?string $textHinweis = null): AbErkennung
    {
        throw new AbErkennungFehler('Es ist keine KI-Erkennung eingerichtet.');
    }
}
