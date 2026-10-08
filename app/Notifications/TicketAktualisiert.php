<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Mail\Mailable;

/**
 * Ereignisse im Ticketsystem (neu, Kommentar, Zuweisung, geschlossen, wieder geöffnet).
 * Das jeweilige Ticket-Mail-Template wird als Mailable mitgegeben.
 */
class TicketAktualisiert extends Benachrichtigung
{
    public function __construct(
        public Ticket $ticket,
        public string $titelText,
        public string $nachricht,
        public ?Mailable $mail = null,
    ) {
    }

    public function kategorie(): string
    {
        return 'tickets';
    }

    public function titel(object $notifiable): string
    {
        return $this->titelText;
    }

    public function text(object $notifiable): string
    {
        return $this->nachricht;
    }

    public function url(object $notifiable): ?string
    {
        return route('tickets.show', $this->ticket);
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return $this->mail;
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['ticket_id' => $this->ticket->id];
    }
}
