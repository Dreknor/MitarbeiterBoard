<?php

namespace App\Notifications;

use App\Models\ErgebnisKarte;

/**
 * Eine Person wurde auf einer Karte eines Ergebnisboards als zuständig eingetragen.
 */
class ErgebnisKarteZugewiesen extends Benachrichtigung
{
    protected bool $keineMailBeiAbwesenheit = true;

    public function __construct(public ErgebnisKarte $karte, public ?string $von = null)
    {
    }

    public function kategorie(): string
    {
        return 'aufgaben';
    }

    public function titel(object $notifiable): string
    {
        return 'Zuständig auf „'.$this->karte->board->titel.'“';
    }

    public function text(object $notifiable): string
    {
        $text = $this->karte->titel;
        if ($this->karte->faellig_am) {
            $text .= ' (bis '.$this->karte->faellig_am->format('d.m.Y').')';
        }

        return $text.($this->von ? ' – eingetragen von '.$this->von : '');
    }

    public function aktionText(): string
    {
        return 'Board öffnen';
    }

    public function url(object $notifiable): ?string
    {
        return route('ergebnisboards.show', $this->karte->board_id).'#karte-'.$this->karte->id;
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['ergebnisboard_id' => $this->karte->board_id, 'karte_id' => $this->karte->id];
    }
}
