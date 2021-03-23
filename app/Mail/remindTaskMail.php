<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class remindTaskMail extends Mailable
{
    use Queueable, SerializesModels;

    public $theme;
    public $date;
    public $task;
    public $name;
    public $group;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $date, $task, $theme, $group)
    {
        $this->name = $name;
        $this->date = $date;
        $this->task = $task;
        $this->theme = $theme;
        $this->group = $group;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Ausstehende Aufgabe')->view('mails.remindTaskMail', [
            'name' =>$this->name,
            'date' =>$this->date,
            'task' =>$this->task,
            'theme' =>$this->theme,
            'group' =>$this->group,
        ]);
    }
}
