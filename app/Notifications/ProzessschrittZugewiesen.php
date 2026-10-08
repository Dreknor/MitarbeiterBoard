<?php

namespace App\Notifications;

use App\Mail\newStepMail;
use App\Models\Procedure_Step;
use Illuminate\Mail\Mailable;

/**
 * Ein Prozessschritt ist für die Person fällig geworden.
 */
class ProzessschrittZugewiesen extends Benachrichtigung
{
    protected bool $keineMailBeiAbwesenheit = true;

    public function __construct(
        public Procedure_Step $step,
        public string $faelligAm,
    ) {
    }

    public function kategorie(): string
    {
        return 'prozesse';
    }

    public function titel(object $notifiable): string
    {
        return 'Neuer Prozessschritt';
    }

    public function text(object $notifiable): string
    {
        return '„'.$this->step->name.'“ im Prozess „'.($this->step->procedure?->name ?? '').'“ – bis '.$this->faelligAm;
    }

    public function url(object $notifiable): ?string
    {
        return $this->step->procedure ? url('procedure/'.$this->step->procedure->id.'/start') : null;
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return new newStepMail(
            $notifiable->name,
            $this->faelligAm,
            $this->step->name,
            $this->step->procedure?->name ?? '',
            $this->step->procedure?->id ?? 0
        );
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['step_id' => $this->step->id];
    }
}
