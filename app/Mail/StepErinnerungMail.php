<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StepErinnerungMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $steps;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, Array $steps)
    {
        $this->name = $name;
        $this->steps = $steps;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Ausstehender Auftrag')->view('mails.remindStepMail', [
            'name' =>$this->name,
            'steps' =>$this->steps,
        ]);
    }
}
