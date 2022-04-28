<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TerminAbsage extends Mailable
{
    use Queueable, SerializesModels;

    public $liste;
    public $termin;
    public $user;
    protected $text;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $liste, $termin, $text = "")
    {
        $this->liste = $liste;
        $this->termin = $termin;
        $this->user = $user;
        $this->text = $text;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(
            $this->user->email,
            $this->user->name
        )
            ->subject('Absage Termin: '.$this->termin->format('d.m.Y H:i'))
            ->view('listen.emails.terminAbsage')
            ->with([
                'termin' => $this->termin,
                'liste' => $this->liste,
                'user' => $this->user,
                'text' => $this->text
            ]);
    }
}
