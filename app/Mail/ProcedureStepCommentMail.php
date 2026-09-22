<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Benachrichtigung über einen neuen Kommentar an einem Prozess-Schritt (§8.3).
 */
class ProcedureStepCommentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $authorName,
        public string $stepName,
        public string $procedureName,
        public int    $procedureId,
        public string $body,
    ) {}

    public function build()
    {
        return $this
            ->subject("Neuer Kommentar zu '{$this->stepName}'")
            ->view('mails.procedureStepCommentMail', [
                'recipientName' => $this->recipientName,
                'authorName'    => $this->authorName,
                'stepName'      => $this->stepName,
                'procedureName' => $this->procedureName,
                'procedureId'   => $this->procedureId,
                'body'          => $this->body,
            ]);
    }
}

