<?php

namespace App\Services\Arbeitsblaetter\Ki;

use App\Support\AbStruktur;

/**
 * Testtreiber: liefert eine feste Struktur (oder `$struktur`), merkt sich die Aufrufe
 * und kann einen Fehler werfen. Wird in Tests als Singleton gebunden.
 */
class FakeErkenner implements AbErkenner
{
    public ?array $struktur = null;

    public ?AbErkennungFehler $fehler = null;

    public int $kostenCent = 3;

    /** @var array<int, array{seiten: array, text: ?string}> */
    public array $aufrufe = [];

    public function konfiguriert(): bool
    {
        return true;
    }

    public function anbieter(): string
    {
        return 'fake';
    }

    public function modell(): string
    {
        return 'fake-1';
    }

    public function erkennen(array $seiten, ?string $textHinweis = null): AbErkennung
    {
        $this->aufrufe[] = ['seiten' => $seiten, 'text' => $textHinweis];
        if ($this->fehler) {
            throw $this->fehler;
        }

        $struktur = $this->struktur ?? [
            'seiten' => count($seiten),
            'bausteine' => [
                ['typ' => 'ueberschrift', 'text' => 'Brüche'],
                ['typ' => 'anweisung', 'text' => 'Ergänze die fehlenden Zahlen.'],
                ['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [['typ' => 'text', 'text' => '3/4 = '], ['typ' => 'luecke', 'laenge' => 4], ['typ' => 'text', 'text' => '/8']]],
                ['typ' => 'bild', 'beschreibung' => 'Kreis, ¾ gefärbt', 'quelle' => ['seite' => 1, 'box' => [0.1, 0.4, 0.5, 0.7]]],
            ],
        ];

        return new AbErkennung(AbStruktur::normalisieren($struktur), max(1, count($seiten)), $this->kostenCent, 'fake', 'fake-1');
    }
}
