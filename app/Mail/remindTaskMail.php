<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        Log::debug('Mail Austehende Prozess-Schritte: ', ['name' => $this->name, 'tasks' => $this->tasks]);;
        return $this->subject('Ausstehende Prozess-Schritte')->view('mails.remindTaskMail', [
            'name' =>$this->name,
            'tasks' =>$this->tasks,
        ]);
    }
}
