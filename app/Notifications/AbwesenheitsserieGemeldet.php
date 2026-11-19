<?php

namespace App\Notifications;

use App\Mail\NewAbsenceMail;
use App\Models\AbsenceSerie;
use Illuminate\Mail\Mailable;

/**
 * Sofortmeldung einer neuen wiederkehrenden Abwesenheit – eine Meldung pro Serie statt pro Termin.
 * Empfänger wie AbwesenheitGemeldet (Kategorie „Abwesenheiten“).
 */
class AbwesenheitsserieGemeldet extends Benachrichtigung
{
    protected bool $keineMailBeiAbwesenheit = true;

    public function __construct(public AbsenceSerie $serie, public int $anzahl)
    {
    }

    public function kategorie(): string
    {
        return 'abwesenheiten';
    }

    public function titel(object $notifiable): string
    {
        return 'Neue wiederkehrende Abwesenheit: '.$this->serie->user?->name;
    }

    public function text(object $notifiable): string
    {
        return $this->serie->user?->name.' ist '.$this->serie->beschreibung().' abwesend ('
            .$this->anzahl.' '.($this->anzahl === 1 ? 'Termin' : 'Termine').').';
    }

    public function url(object $notifiable): ?string
    {
        return url('absences');
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return new NewAbsenceMail(
            $this->serie->user?->name,
            $this->serie->start->format('d.m.Y'),
            $this->serie->end->format('d.m.Y'),
            $this->serie->reason.' ('.$this->serie->rhythmusText().', '.$this->anzahl.' Termine)'
        );
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['absence_serie_id' => $this->serie->id];
    }
}
