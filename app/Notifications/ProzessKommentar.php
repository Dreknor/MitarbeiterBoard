<?php

namespace App\Notifications;

use App\Mail\ProcedureStepCommentMail;
use App\Models\Procedure_Step;
use App\Models\ProcedureStepComment;
use Illuminate\Mail\Mailable;

/**
 * Neuer Kommentar zu einem Prozessschritt, an dem die Person beteiligt ist.
 */
class ProzessKommentar extends Benachrichtigung
{
    protected bool $keineMailBeiAbwesenheit = true;

    public function __construct(
        public ProcedureStepComment $comment,
        public Procedure_Step $step,
    ) {
    }

    public function kategorie(): string
    {
        return 'prozesse';
    }

    public function titel(object $notifiable): string
    {
        return 'Kommentar zu „'.$this->step->name.'“';
    }

    public function text(object $notifiable): string
    {
        return ($this->comment->user?->name ?? 'System').': '.\Illuminate\Support\Str::limit(strip_tags((string) $this->comment->body), 140);
    }

    public function url(object $notifiable): ?string
    {
        return $this->step->procedure ? url('procedure/'.$this->step->procedure->id.'/start') : null;
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return new ProcedureStepCommentMail(
            recipientName: $notifiable->name,
            authorName:    $this->comment->user?->name ?? 'System',
            stepName:      $this->step->name,
            procedureName: $this->step->procedure?->name ?? '',
            procedureId:   $this->step->procedure?->id ?? 0,
            body:          $this->comment->body
        );
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['step_id' => $this->step->id, 'comment_id' => $this->comment->id];
    }
}
