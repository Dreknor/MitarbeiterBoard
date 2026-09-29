<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use \Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Str;

class SendMonthlyTimesheetMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;
    protected $date;
    protected string $pdf;

    /**
     * Create a new message instance.
     *
     * Die PDF wird direkt eingelesen – die temporäre Datei darf danach gelöscht werden
     * (keine gemeinsam genutzte Datei mehr, keine Race Condition zwischen Empfängern).
     */
    public function __construct(User $user, Carbon $date, string $pdfPath)
    {
        $this->user = $user;
        $this->date = $date;
        $this->pdf = base64_encode((string) file_get_contents($pdfPath));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Arbeitszeitnachweis '.$this->date->format('m/Y'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'personal.timesheets.sendMail',
            with: [
                'user' => $this->user,
                'date' => $this->date,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => base64_decode($this->pdf), 'Arbeitszeitnachweis_'.Str::slug($this->user->name).'_'.$this->date->format('m_Y').'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
