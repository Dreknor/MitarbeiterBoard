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
    public $date;
    public $procedure;
    public $procedureId;
    public $step;
    public $stepId;
    public $group;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $date, $procedureName, $procedureId, $step, $stepId)
    {
        $this->name = $name;
        $this->date = $date;
        $this->procedure = $procedureName;
        $this->procedureId = $procedureId;
        $this->step = $step;
        $this->stepId = $stepId;
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
            'date' =>$this->date,
            'procedure' =>$this->procedure,
            'procedureID' =>$this->procedureId,
            'step' =>$this->step,
            'stepId' =>$this->stepId,
        ]);
    }
}
