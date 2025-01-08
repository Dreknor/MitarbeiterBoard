<?php

namespace App\Mail;

use App\Models\Protocol;
use App\Models\Theme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

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

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: new Address($this->protocol->user->email,$this->protocol->user->name),
            subject: 'Neues Protokoll für Thema: ' . $this->theme,
        );
    }


    public function content(): Content
    {
        return new Content(
            view: 'mails.newProtocolForTask',
            with: [
                'name' =>$this->name,
                'theme' =>$this,
                'theme_id' =>$this->theme_id,
                'groupname' => $this->groupname,
                'protocol' => $this->protocol
            ]
        );
    }


}
