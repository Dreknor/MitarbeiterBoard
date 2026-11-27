<?php

namespace Tests\Fakes;

use App\Services\Arbeitsblaetter\AbKonvertierung;

/** Word → PDF ohne Nextcloud: liefert ein kleines PDF oder (verfuegbar = false) nichts. */
class AbKonvertierungFake implements AbKonvertierung
{
    public bool $verfuegbar = true;

    /** @var array<int, string> */
    public array $aufrufe = [];

    public function verfuegbar(): bool
    {
        return $this->verfuegbar;
    }

    public function bezeichnung(): string
    {
        return 'Test';
    }

    public function zuPdf(string $inhalt, string $dateiname): ?string
    {
        $this->aufrufe[] = $dateiname;

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>Umgewandelt: '.e($dateiname).'</p>')->output();
    }
}
