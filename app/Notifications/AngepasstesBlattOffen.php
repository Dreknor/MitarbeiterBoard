<?php

namespace App\Notifications;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;

/** Erinnerung: Ein Arbeitsblatt liegt seit langem ohne Freigabe in der Bibliothek. */
class AngepasstesBlattOffen extends Benachrichtigung
{
    public function __construct(public AngepasstesBlatt $blatt, public int $tage)
    {
    }

    public function kategorie(): string
    {
        return 'arbeitsblaetter';
    }

    public function titel(object $notifiable): string
    {
        return 'Arbeitsblatt noch nicht freigegeben';
    }

    public function text(object $notifiable): string
    {
        return '„'.$this->blatt->titel.'“ liegt seit über '.$this->tage.' Tagen ohne Freigabe in der Bibliothek. Freigeben oder löschen?';
    }

    public function url(object $notifiable): ?string
    {
        return route('arbeitsblaetter.edit', $this->blatt);
    }
}
