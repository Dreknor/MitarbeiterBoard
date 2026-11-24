<?php

namespace App\Notifications;

use App\Models\ErgebnisKarte;

/**
 * Erinnerung an eine bald (morgen) oder heute fällige Karte eines Ergebnisboards.
 */
class ErgebnisKarteFaellig extends Benachrichtigung
{
    protected bool $keineMailBeiAbwesenheit = true;

    public function __construct(public ErgebnisKarte $karte)
    {
    }

    public function kategorie(): string
    {
        return 'aufgaben';
    }

    public function titel(object $notifiable): string
    {
        return $this->karte->faellig_am?->isToday() ? 'Heute fällig' : 'Morgen fällig';
    }

    public function text(object $notifiable): string
    {
        return $this->karte->titel.' – Board „'.$this->karte->board->titel.'“';
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
