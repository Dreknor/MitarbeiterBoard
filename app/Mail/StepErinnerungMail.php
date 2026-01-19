<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

        Log::debug('StepErinnerungMail constructor', [
            'name' => $this->name,
            'steps' => $this->steps,
        ]);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        Log::debug('StepErinnerungMail build', [
            'name' => $this->name,
            'steps' => $this->steps,
        ]);

        return $this->subject('Offene Prozessschritte')
            ->view('mails.remindStepMail', [
                'name' => $this->name,
                'steps' => $this->steps,
            ]);
    }
}
