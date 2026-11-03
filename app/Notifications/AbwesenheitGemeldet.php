<?php

namespace App\Notifications;

use App\Mail\NewAbsenceMail;
use App\Models\Absence;
use Illuminate\Mail\Mailable;

/**
 * Sofortmeldung einer neuen Abwesenheit – nur an Personen mit „view absences“,
 * die die Kategorie „Abwesenheiten“ (Push oder Mail) eingeschaltet haben.
 */
class AbwesenheitGemeldet extends Benachrichtigung
{
    protected bool $keineMailBeiAbwesenheit = true;

    public function __construct(public Absence $absence)
    {
    }

    public function kategorie(): string
    {
        return 'abwesenheiten';
    }

    public function titel(object $notifiable): string
    {
        return 'Neue Abwesenheit: '.$this->absence->user?->name;
    }

    public function text(object $notifiable): string
    {
        return $this->absence->user?->name.' ist vom '.$this->absence->start->format('d.m.Y')
            .' bis '.$this->absence->end->format('d.m.Y').' abwesend.';
    }

    public function url(object $notifiable): ?string
    {
        return url('absences');
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return new NewAbsenceMail(
            $this->absence->user?->name,
            $this->absence->start->format('d.m.Y'),
            $this->absence->end->format('d.m.Y'),
            $this->absence->reason
        );
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['absence_id' => $this->absence->id];
    }
}
