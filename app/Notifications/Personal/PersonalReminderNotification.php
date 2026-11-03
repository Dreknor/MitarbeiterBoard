<?php

namespace App\Notifications\Personal;

use App\Models\personal\PersonalReminder;
use App\Notifications\Benachrichtigung;

class PersonalReminderNotification extends Benachrichtigung
{
    public function __construct(private readonly PersonalReminder $reminder) {}

    public function kategorie(): string
    {
        return 'personal';
    }

    public function titel(object $notifiable): string
    {
        return "Wiedervorlage Personal: {$this->reminder->label()} ({$this->reminder->employe->name})";
    }

    public function text(object $notifiable): string
    {
        return "{$this->reminder->employe->name}: {$this->reminder->label()} am {$this->reminder->due_date->format('d.m.Y')}.";
    }

    public function zeilen(object $notifiable): array
    {
        return array_values(array_filter([
            $this->text($notifiable),
            (string) $this->reminder->note,
        ]));
    }

    public function url(object $notifiable): ?string
    {
        return route('personal.personalakte.show', $this->reminder->employe_id);
    }

    public function aktionText(): string
    {
        return 'Personalakte öffnen';
    }

    public function zusatzdaten(object $notifiable): array
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
