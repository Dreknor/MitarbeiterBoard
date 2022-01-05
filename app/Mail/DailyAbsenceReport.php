<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyAbsenceReport extends Mailable
{
    use Queueable, SerializesModels;

    public $absences;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($absences)
    {
        $this->absences = $absences;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('Abwesenheiten am '.Carbon::now()->format('d.m.Y'))
            ->view('mails.dailyAbsenceReport', [
                'absences'    =>$this->absences,
            ]);
    }
}
