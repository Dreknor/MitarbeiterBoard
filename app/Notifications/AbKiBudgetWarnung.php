<?php

namespace App\Notifications;

/** KI-Kontingent der Angepassten Arbeitsblätter erreicht die Warnschwelle bzw. ist aufgebraucht. */
class AbKiBudgetWarnung extends Benachrichtigung
{
    public function __construct(public int $prozent, public bool $gesperrt)
    {
    }

    public function kategorie(): string
    {
        return 'system';
    }

    public function titel(object $notifiable): string
    {
        return $this->gesperrt ? 'KI-Kontingent aufgebraucht' : 'KI-Kontingent zu '.$this->prozent.' % genutzt';
    }

    public function text(object $notifiable): string
    {
        return $this->gesperrt
            ? 'Angepasste Arbeitsblätter: Bis Monatsende werden Blätter nur noch manuell erfasst. Das Limit lässt sich in den Einstellungen anpassen.'
            : 'Angepasste Arbeitsblätter: '.$this->prozent.' % des monatlichen KI-Kontingents sind verbraucht.';
    }

    public function url(object $notifiable): ?string
    {
        return route('arbeitsblaetter.einstellungen');
    }
}
