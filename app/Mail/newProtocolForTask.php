<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class newProtocolForTask extends Mailable
{
    use Queueable, SerializesModels;

    public $theme;
    public $name;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $theme)
    {
        $this->name = $name;
        $this->theme = $theme;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('neues Protokoll')->view('mails.newProtocolForTask',[
            "name" =>$this->name,
            "theme" =>$this->theme
        ]);
    }
}
