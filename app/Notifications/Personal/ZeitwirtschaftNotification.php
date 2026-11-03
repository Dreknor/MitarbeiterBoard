<?php

namespace App\Notifications\Personal;

use App\Notifications\Benachrichtigung;

/**
 * Benachrichtigung für Urlaub, Arbeitszeitnachweis und Dienstplan.
 * Die Kategorie ergibt sich aus dem Präfix des Typs (holiday_, roster_, timesheet_).
 */
class ZeitwirtschaftNotification extends Benachrichtigung
{
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

    public function kategorie(): string
    {
        return match (true) {
            str_starts_with($this->type, 'holiday') => 'urlaub',
            str_starts_with($this->type, 'roster') => 'dienstplan',
            default => 'zeiterfassung',
        };
    }

    public function titel(object $notifiable): string
    {
        return $this->subject;
    }

    public function text(object $notifiable): string
    {
        return $this->subject.($this->lines ? ' – '.$this->lines[0] : '');
    }

    public function zeilen(object $notifiable): array
    {
        return $this->lines;
    }

    public function url(object $notifiable): ?string
    {
        return $this->actionUrl;
    }

    public function aktionText(): string
    {
        return $this->actionText;
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['type' => $this->type];
    }
}
