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
     * @param array<string, string> $files Dateiname => PDF-Inhalt (Binärdaten)
     */
    public function __construct($vorname, $nachname, $date, $absender, array $files)
    {

        $this->vorname = $vorname;
        $this->nachname = $nachname;
        $this->date = $date;
        $this->absender = $absender;
        // Inhalte statt Dateipfaden: die Mail kann gequeued werden, ohne dass temporäre
        // Dateien liegen bleiben oder zwischen Empfängern überschrieben werden.
        $this->files = array_map('base64_encode', $files);
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

        foreach ($this->files as $name => $inhalt) {
            $view->attachData(base64_decode($inhalt), $name, ['mime' => 'application/pdf']);
        }

        return $view;
    }
}
