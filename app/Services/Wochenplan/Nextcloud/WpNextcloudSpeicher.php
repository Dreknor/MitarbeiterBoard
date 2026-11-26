<?php

namespace App\Services\Wochenplan\Nextcloud;

/**
 * Zugriff auf die Nextcloud-Ablage des Wochenplans (Dienst-Konto, WebDAV).
 * Alle Pfade sind relativ zum Basisordner aus den Einstellungen.
 * In Tests wird {@see \Tests\Fakes\WpNextcloudFake} gebunden.
 */
interface WpNextcloudSpeicher
{
    /** Ist die Ablage eingeschaltet und konfiguriert? */
    public function aktiv(): bool;

    /** Legt den Ordner inkl. aller Elternordner an. */
    public function ordnerAnlegen(string $pfad): bool;

    /** Lädt Inhalt hoch (überschreibt vorhandene Dateien). */
    public function hochladen(string $inhalt, string $pfad): bool;

    /** @return string|false Inhalt oder false, wenn nicht vorhanden/Fehler */
    public function herunterladen(string $pfad): string|false;

    /** Verschiebt eine Datei oder einen Ordner; Zielordner werden angelegt. */
    public function verschieben(string $von, string $nach): bool;

    public function existiert(string $pfad): bool;

    public function loeschen(string $pfad): bool;

    /**
     * Inhalt eines Ordners.
     *
     * @return array<int, array{name: string, pfad: string, ordner: bool, groesse: int, mime_type: ?string}>
     */
    public function liste(string $pfad): array;

    /** Link zur Datei bzw. zum Ordner in der Nextcloud-Oberfläche. */
    public function webLink(string $pfad, bool $istOrdner = false): ?string;
}
