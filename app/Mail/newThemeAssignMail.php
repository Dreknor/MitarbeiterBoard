<?php

namespace App\Mail;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class newThemeAssignMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $assigned_to;
    public $theme;

    public function __construct(Theme $theme, User $assigned_to)
    {
        $this->theme = $theme;
        $this->assigned_to = $assigned_to;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thema zugewiesen: '.$this->theme->theme,
            to: $this->assigned_to->email,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-theme-assign',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
