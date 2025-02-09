<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewTicketCommentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $comment;
    public $ticket;

    /**
     * Create a new message instance.
     */
    public function __construct(TicketComment $comment, Ticket $ticket)
    {
        $this->comment = $comment;
        $this->ticket = $ticket;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neuer Kommentar zu Ticket: ' . $this->ticket->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.commentNotification',
            with: ['comment' => $this->comment, 'ticket' => $this->ticket],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
