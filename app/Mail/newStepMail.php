<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class newStepMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $procedure;
    public $date;
    public $step;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $date, $step, $procedure)
    {
        $this->name = $name;
        $this->date = $date;
        $this->step = $step;
        $this->procedure = $procedure;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('neuer Prozessfortschritt')->view('mails.newStepMail', [
            'name' =>$this->name,
            'date' =>$this->date,
            'step' =>$this->step,
            'procedure' =>$this->procedure,
        ]);
    }
}
