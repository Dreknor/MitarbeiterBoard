<?php

namespace App\Notifications\Personal;

use App\Models\personal\PersonalReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PersonalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PersonalReminder $reminder) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->reminder->employe->name;

        return (new MailMessage)
            ->subject("Wiedervorlage Personal: {$this->reminder->label()} ({$name})")
            ->greeting('Wiedervorlage in der Personalverwaltung')
            ->line("{$name}: {$this->reminder->label()} am {$this->reminder->due_date->format('d.m.Y')}.")
            ->line((string) $this->reminder->note)
            ->action('Personalakte öffnen', route('personal.personalakte.show', $this->reminder->employe_id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'personal_reminder',
            'reminder_id' => $this->reminder->id,
            'reminder'    => $this->reminder->type,
            'employe_id'  => $this->reminder->employe_id,
            'due_date'    => $this->reminder->due_date->format('Y-m-d'),
        ];
    }
}
