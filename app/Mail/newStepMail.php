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
    private Int $procedure_id;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(String $name, String $date, String $step, String $procedure, Int $procedure_id)
    {
        $this->name = $name;
        $this->date = $date;
        $this->step = $step;
        $this->procedure = $procedure;
        $this->procedure_id = $procedure_id;
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
            'procedure_id'  => $this->procedure_id
        ]);
    }
}
