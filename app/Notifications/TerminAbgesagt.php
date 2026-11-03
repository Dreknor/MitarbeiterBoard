<?php

namespace App\Notifications;

use App\Mail\TerminAbsage;
use App\Models\Liste;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;

/**
 * Ein reservierter Termin einer Terminliste wurde abgesagt oder gelöscht.
 */
class TerminAbgesagt extends Benachrichtigung
{
    public function __construct(
        public Liste $liste,
        public Carbon $termin,
        public User $absagende,
        public string $begruendung = '',
    ) {
    }

    public function kategorie(): string
    {
        return 'terminlisten';
    }

    public function titel(object $notifiable): string
    {
        return 'Termin abgesagt: '.$this->termin->format('d.m.Y H:i');
    }

    public function text(object $notifiable): string
    {
        return $this->absagende->name.' hat den Termin am '.$this->termin->format('d.m.Y H:i')
            .' („'.$this->liste->listenname.'“) abgesagt.'
            .($this->begruendung !== '' ? ' '.$this->begruendung : '');
    }

    public function url(object $notifiable): ?string
    {
        return url('listen/'.$this->liste->id);
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return new TerminAbsage($this->absagende, $this->liste, $this->termin, $this->begruendung);
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['liste_id' => $this->liste->id];
    }
}
