<?php

namespace App\Notifications\Personal;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigung für Urlaub, Arbeitszeitnachweis und Dienstplan
 * (Mail + Datenbank → erscheint auch auf der Dashboard-Karte "Benachrichtigungen").
 */
class ZeitwirtschaftNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param string[] $lines
     */
    public function __construct(
        public readonly string $type,
        public readonly string $subject,
        public readonly array $lines,
        public readonly ?string $actionUrl = null,
        public readonly string $actionText = 'Öffnen',
    ) {
    }

    public function via(object $notifiable): array
    {
        return empty($notifiable->email) ? ['database'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting('Hallo '.($notifiable->vorname ?? $notifiable->name ?? '').',');

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        if ($this->actionUrl !== null) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'subject' => $this->subject,
            'message' => $this->subject.($this->lines ? ' – '.$this->lines[0] : ''),
            'url' => $this->actionUrl,
        ];
    }
}
