<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigung für Admins bei 3+ aufeinanderfolgenden Sync-Fehlern.
 *
 * Channels: mail + database
 * Ausgelöst durch: OxCalendarService::checkConsecutiveErrors()
 */
class SyncFailedNotification extends Notification
{
    use Queueable;

    protected int $fehlerAnzahl;
    protected string $letzterFehler;

    public function __construct(int $fehlerAnzahl, string $letzterFehler)
    {
        $this->fehlerAnzahl  = $fehlerAnzahl;
        $this->letzterFehler = $letzterFehler;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('⚠️ Kalender-Synchronisation fehlgeschlagen')
            ->greeting('Hallo ' . $notifiable->name . ',')
            ->line("Die Kalender-Synchronisation mit Open-Xchange ist {$this->fehlerAnzahl}x hintereinander fehlgeschlagen.")
            ->line("Letzter Fehler: {$this->letzterFehler}")
            ->action('Sync-Logs prüfen', route('calendar.admin.logs', ['aktion' => 'error']))
            ->line('Bitte prüfen Sie die OX-Verbindung und die CalDAV-Konfiguration.')
            ->salutation('MitarbeiterBoard');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'typ'           => 'calendar_sync_failed',
            'fehler_anzahl' => $this->fehlerAnzahl,
            'letzter_fehler' => $this->letzterFehler,
            'nachricht'     => "Kalender-Sync {$this->fehlerAnzahl}x fehlgeschlagen: {$this->letzterFehler}",
        ];
    }
}

