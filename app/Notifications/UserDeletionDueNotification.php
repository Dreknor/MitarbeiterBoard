<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Löschkonzept: informiert Admins über Konten, für die eine Entscheidung ansteht.
 * Ausgeführt wird nichts automatisch, die Bestätigung erfolgt auf der Auswirkungsübersicht.
 */
class UserDeletionDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Collection $legacy,
        private readonly Collection $anonymize,
        private readonly Collection $purge,
        private readonly int $fuehrungszeugnisse = 0,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Löschkonzept: Entscheidungen zu ausgeschiedenen Mitarbeitern')
            ->greeting('Hallo,');

        if ($this->legacy->isNotEmpty()) {
            $mail->line('Bestehende deaktivierte Konten aus der Zeit vor dem Löschkonzept, bitte jeweils entscheiden:');
            foreach ($this->legacy as $user) {
                $mail->line('- '.$user->name.' (deaktiviert seit '.optional($user->left_at)->format('d.m.Y').')');
            }
        }

        if ($this->anonymize->isNotEmpty()) {
            $mail->line('Sperrfrist abgelaufen, Anonymisierung (Stufe 2) zur Bestätigung:');
            foreach ($this->anonymize as $user) {
                $mail->line('- '.$user->name.' (Austritt '.optional($user->left_at)->format('d.m.Y').')');
            }
        }

        if ($this->purge->isNotEmpty()) {
            $mail->line('Aufbewahrungsfristen abgelaufen, endgültige Löschung (Stufe 3) zur Bestätigung:');
            foreach ($this->purge as $user) {
                $mail->line('- Konto #'.$user->id.' (Austritt '.optional($user->left_at)->format('d.m.Y').')');
            }
        }

        if ($this->fuehrungszeugnisse > 0) {
            $mail->line($this->fuehrungszeugnisse.' Eintrag/Einträge zum Führungszeugnis wurden nach § 72a SGB VIII automatisch gelöscht.');
        }

        return $mail
            ->action('Ausgeschiedene Mitarbeiter öffnen', url('user-deletion'))
            ->line('Vor jeder Ausführung zeigt das Board die vollständige Übersicht der Auswirkungen.');
    }
}
