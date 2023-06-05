<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class remindTaskMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $tasks;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $tasks)
    {
        $this->name = $name;
        $this->tasks = $tasks;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Ausstehende Aufgaben')->view('mails.remindTaskMail', [
            'name' =>$this->name,
            'tasks' =>$this->tasks,
        ]);
    }
}
