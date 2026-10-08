<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tagesübersicht „Dein Tag“ (morgens oder am Vorabend).
 * $bereiche ist bewusst ein einfaches Array (queue-sicher), siehe TagesvorschauService::alsArray().
 */
class Tagesvorschau extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public Carbon $zieltag,
        public bool $vorabend,
        public array $bereiche,
    ) {
    }

    public function envelope(): Envelope
    {
        $datum = $this->zieltag->locale('de')->isoFormat('dddd, D.M.');

        return new Envelope(
            subject: $this->vorabend ? 'Dein Tag morgen – '.$datum : 'Dein Tag heute – '.$datum,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mails.tagesvorschau',
            with: [
                'datum'            => $this->zieltag->locale('de')->isoFormat('dddd, D. MMMM YYYY'),
                'tagUrl'           => route('benachrichtigungen.tag', $this->zieltag->toDateString()),
                'einstellungenUrl' => route('benachrichtigungen.einstellungen'),
            ],
        );
    }
}
