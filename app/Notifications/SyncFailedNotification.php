<?php

namespace App\Notifications;

/**
 * Benachrichtigung für Admins bei 3+ aufeinanderfolgenden Sync-Fehlern.
 *
 * Ausgelöst durch: OxCalendarService::checkConsecutiveErrors()
 */
class SyncFailedNotification extends Benachrichtigung
{
    public function __construct(
        protected int $fehlerAnzahl,
        protected string $letzterFehler,
    ) {
    }

    public function kategorie(): string
    {
        return 'system';
    }

    public function titel(object $notifiable): string
    {
        return '⚠️ Kalender-Synchronisation fehlgeschlagen';
    }

    public function text(object $notifiable): string
    {
        return "Kalender-Sync {$this->fehlerAnzahl}x fehlgeschlagen: {$this->letzterFehler}";
    }

    public function zeilen(object $notifiable): array
    {
        return [
            "Die Kalender-Synchronisation mit Open-Xchange ist {$this->fehlerAnzahl}x hintereinander fehlgeschlagen.",
            "Letzter Fehler: {$this->letzterFehler}",
            'Bitte prüfen Sie die OX-Verbindung und die CalDAV-Konfiguration.',
        ];
    }

    public function url(object $notifiable): ?string
    {
        return route('calendar.admin.logs', ['aktion' => 'error']);
    }

    public function aktionText(): string
    {
        return 'Sync-Logs prüfen';
    }

    public function zusatzdaten(object $notifiable): array
    {
        return [
            'typ'            => 'calendar_sync_failed',
            'fehler_anzahl'  => $this->fehlerAnzahl,
            'letzter_fehler' => $this->letzterFehler,
        ];
    }
}
