<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendRosterMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $vorname;
    protected $date;
    protected $nachname;
    protected $absender;
    protected $files;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($vorname, $nachname, $date, $absender, array $files)
    {

        $this->vorname = $vorname;
        $this->nachname = $nachname;
        $this->date = $date;
        $this->absender = $absender;
        $this->files = $files;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $view = $this
            ->subject('Dienstplan')
            ->view('personal.rosters.mails.sendRoster', [
            'vorname' => $this->vorname,
            'nachname' => $this->nachname,
            'date' => $this->date,
            'absender' => $this->absender,
        ]);

        foreach ($this->files as $file) {
            $view->attach(storage_path($file));
        }

        return $view;
    }
}
