<?php

namespace App\Notifications;

use App\Mail\newTaskMail;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Task;
use Illuminate\Mail\Mailable;

/**
 * Eine Person wurde für eine (persönliche oder gemeinsame) Aufgabe zuständig.
 */
class AufgabeZugewiesen extends Benachrichtigung
{
    protected bool $keineMailBeiAbwesenheit = true;

    public function __construct(public Task $task)
    {
    }

    public function kategorie(): string
    {
        return 'aufgaben';
    }

    public function titel(object $notifiable): string
    {
        return $this->task->isCollective() ? 'Neue gemeinsame Aufgabe' : 'Neue Aufgabe';
    }

    public function text(object $notifiable): string
    {
        return $this->task->task.' (bis '.$this->task->date->format('d.m.Y').')';
    }

    public function url(object $notifiable): ?string
    {
        return $this->task->themeUrl();
    }

    public function mailable(object $notifiable): ?Mailable
    {
        $label = match ($this->task->taskable_type) {
            Group::class   => $this->task->taskable?->name,
            Meeting::class => 'Meeting „'.$this->task->taskable?->title.'“',
            default        => null,
        };

        return new newTaskMail(
            $notifiable->name,
            $this->task->date->format('d.m.Y'),
            $this->task->task,
            $this->task->theme?->theme,
            $this->task->isCollective(),
            $label,
            $this->task->themeUrl()
        );
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['task_id' => $this->task->id];
    }
}
