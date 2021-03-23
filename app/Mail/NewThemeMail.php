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
    public $theme_id;
    public $groupname;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($theme, $theme_id, $groupname)
    {
        $this->theme = $theme;
        $this->theme_id = $theme_id;
        $this->groupname = $groupname;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->groupname.': neues Thema')->view('mails.newThemeMail', [
            'theme' =>$this->theme,
            'groupname' =>$this->groupname,
            'theme_id' => $this->theme_id,
        ]);
    }
}
