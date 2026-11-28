<?php

namespace App\Services\Arbeitsblaetter\Ki;

/**
 * KI-Erkennung eines Arbeitsblatts als Bausteine.
 *
 * Gesendet werden nur Seitenbilder bzw. das PDF und der Prompt – nie Namen, Klassen oder
 * Sehprofile. Die Antwort geht immer durch {@see \App\Support\AbStruktur::normalisieren()}.
 * Treiber werden über `config('arbeitsblaetter.ki.treiber')` gewählt ({@see AbErkennerFabrik}).
 */
interface AbErkenner
{
    /** Ist der Treiber eingerichtet (Schlüssel, Modell)? */
    public function konfiguriert(): bool;

    public function anbieter(): string;

    public function modell(): string;

    /**
     * @param  array<int, array{inhalt: string, mime: string}>  $seiten  Seitenbilder (JPEG/PNG) oder ein PDF
     * @param  string|null  $textHinweis  Textebene eines PDFs (Hilfe für die Erkennung)
     *
     * @throws AbErkennungFehler
     */
    public function erkennen(array $seiten, ?string $textHinweis = null): AbErkennung;
}
