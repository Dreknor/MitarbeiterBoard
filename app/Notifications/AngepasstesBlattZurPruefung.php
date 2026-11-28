<?php

namespace App\Notifications;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;

/** Ein Arbeitsblatt wurde erkannt (bzw. braucht manuelle Erfassung) und wartet auf die Prüfung. */
class AngepasstesBlattZurPruefung extends Benachrichtigung
{
    public function __construct(public AngepasstesBlatt $blatt, public bool $manuell = false)
    {
    }

    public function kategorie(): string
    {
        return 'arbeitsblaetter';
    }

    public function titel(object $notifiable): string
    {
        return 'Arbeitsblatt zur Prüfung: '.$this->blatt->titel;
    }

    public function text(object $notifiable): string
    {
        $herkunft = $this->blatt->quelle === 'wochenplan' ? ' aus dem Wochenplan' : '';

        return $this->manuell
            ? 'Das Arbeitsblatt'.$herkunft.' muss manuell erfasst werden (keine KI-Erkennung verfügbar). Danach freigeben.'
            : 'Das Arbeitsblatt'.$herkunft.' wurde erkannt. Bitte prüfen und freigeben – danach entstehen die angepassten Fassungen.';
    }

    public function aktionText(): string
    {
        return 'Blatt prüfen';
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
