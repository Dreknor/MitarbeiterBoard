<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Zusammenfassung ungelesener Benachrichtigungen (Mail-Modus „Zusammenfassung“).
 */
class BenachrichtigungsZusammenfassung extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public array $gruppen,
        public int $anzahl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->anzahl === 1
                ? '1 neue Benachrichtigung im MitarbeiterBoard'
                : $this->anzahl.' neue Benachrichtigungen im MitarbeiterBoard',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mails.benachrichtigungsZusammenfassung',
            with: [
                'indexUrl'         => route('benachrichtigungen.index', ['status' => 'ungelesen']),
                'einstellungenUrl' => route('benachrichtigungen.einstellungen'),
            ],
        );
    }
}
