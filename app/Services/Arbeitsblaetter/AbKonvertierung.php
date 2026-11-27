<?php

namespace App\Services\Arbeitsblaetter;

/**
 * Umwandlung von Word-/ODT-Dateien in PDF (Standard: Nextcloud + Collabora,
 * {@see NextcloudKonvertierung}). In Tests wird {@see \Tests\Fakes\AbKonvertierungFake} gebunden.
 */
interface AbKonvertierung
{
    /** Ist eine Umwandlung eingerichtet? */
    public function verfuegbar(): bool;

    /** Kurzbeschreibung für Einstellungen/Prüfbefehl, z. B. „Nextcloud (Collabora)“. */
    public function bezeichnung(): string;

    /** PDF-Inhalt oder null bei Fehler. */
    public function zuPdf(string $inhalt, string $dateiname): ?string;
}
