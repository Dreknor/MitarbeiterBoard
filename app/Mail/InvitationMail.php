<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;
    public $group;
    public $date;
    public $themes;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($groupname, $date, $themes)
    {
        $this->group = $groupname;
        $this->date = $date;
        $this->themes = $themes;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Themen der Gruppe '.$this->group)->view('mails.invitation');
    }
}
