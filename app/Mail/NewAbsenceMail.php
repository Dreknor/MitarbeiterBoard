<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewAbsenceMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $username;
    protected $start;
    protected $end;
    protected $reason;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($username, $start, $end, $reason)
    {
        $this->username = $username;
        $this->start = $start;
        $this->end = $end;
        $this->reason = $reason;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('Abwesenheit '.$this->username)
            ->view('mails.absence_abo_now',[
                'username' => $this->username,
                'start' => $this->start,
                'end' => $this->end,
                'reason' => $this->reason,
            ]);
    }
}
