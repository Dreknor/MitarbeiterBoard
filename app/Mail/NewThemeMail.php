<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewThemeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $theme;
    public $groupname;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($theme, $groupname)
    {
        $this->theme = $theme;
        $this->groupname = $groupname;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->groupname.': neues Thema')->view('mails.newThemeMail',[
            "theme" =>$this->theme,
            "groupname" =>$this->groupname,
        ]);
    }
}
