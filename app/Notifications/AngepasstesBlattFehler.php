<?php

namespace App\Notifications;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;

/** Die KI-Erkennung eines Arbeitsblatts ist endgültig fehlgeschlagen. */
class AngepasstesBlattFehler extends Benachrichtigung
{
    public function __construct(public AngepasstesBlatt $blatt)
    {
    }

    public function kategorie(): string
    {
        return 'arbeitsblaetter';
    }

    public function titel(object $notifiable): string
    {
        return 'Erkennung fehlgeschlagen: '.$this->blatt->titel;
    }

    public function text(object $notifiable): string
    {
        return ($this->blatt->fehler ?: 'Die Erkennung ist fehlgeschlagen.').' Das Blatt kann im Editor manuell erfasst werden.';
    }

    public function aktionText(): string
    {
        return 'Blatt öffnen';
    }

    public function url(object $notifiable): ?string
    {
        return route('arbeitsblaetter.edit', $this->blatt);
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['ab_blatt_id' => $this->blatt->id];
    }
}
