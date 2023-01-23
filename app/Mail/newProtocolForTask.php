<?php

namespace App\Mail;

use App\Models\Protocol;
use App\Models\Theme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class newProtocolForTask extends Mailable
{
    use Queueable, SerializesModels;

    public $theme;
    public $theme_id;
    public $name;
    public $groupname;
    public $protocol;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $name, Theme $theme, string $groupname, Protocol $protocol)
    {
        $this->name = $name;
        $this->theme = $theme->theme;
        $this->theme_id = $theme->id;
        $this->groupname = $groupname;
        $this->protocol = $protocol->protocol;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('neues Protokoll')->view('mails.newProtocolForTask', [
            'name' =>$this->name,
            'theme' =>$this,
            'theme_id' =>$this->theme_id,
            'groupname' => $this->groupname,
            'protocol' => $this->protocol
        ]);
    }
}
