<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Sabre\VObject\Component\VCalendar;

class MeetingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $meeting;
    public $group;
    public $user;
    public $messageText;
    public $absender;

    /**
     * Create a new message instance.
     */
    public function __construct(Meeting $meeting, Group $group, User $user, $messageText = null, $absender = null)
    {
        $this->meeting = $meeting;
        $this->group = $group;
        $this->user = $user;
        $this->messageText = $messageText;
        $this->absender = $absender ?: '';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Einladung zum Meeting: ' . $this->meeting->title)
            ->view('mails.meeting_invitation')
            ->attachData(
                $this->buildIcal(),
                'einladung.ics',
                ['mime' => 'text/calendar; charset=UTF-8; method=REQUEST']
            );
    }

    /**
     * Erstellt eine iCalendar-Einladung (RFC 5545) als String.
     */
    private function buildIcal(): string
    {
        $tz       = config('app.timezone', 'Europe/Berlin');
        $date     = $this->meeting->date->format('Y-m-d');
        $dtstart  = \Carbon\Carbon::parse($date . ' ' . $this->meeting->start_time, $tz);
        $dtend    = \Carbon\Carbon::parse($date . ' ' . $this->meeting->end_time, $tz);
        $fromAddr = config('mail.from.address', 'noreply@mitarbeiter.local');

        $vcal = new VCalendar();
        $vcal->add('VEVENT', [
            'UID'         => 'meeting-' . $this->meeting->id . '@mitarbeiter.local',
            'DTSTAMP'     => new \DateTime('now', new \DateTimeZone($tz)),
            'DTSTART'     => $dtstart->toDateTime(),
            'DTEND'       => $dtend->toDateTime(),
            'SUMMARY'     => $this->meeting->title,
            'DESCRIPTION' => strip_tags($this->buildDescription()),
            'ORGANIZER'   => 'mailto:' . $fromAddr,
            'ATTENDEE'    => 'mailto:' . $this->user->email,
            'STATUS'      => 'CONFIRMED',
            'SEQUENCE'    => 0,
        ]);

        // Location aus Meeting-URL der Gruppe, falls vorhanden
        if (!empty($this->group->meeting_url)) {
            $vcal->VEVENT->add('LOCATION', $this->group->meeting_url);
        }

        return $vcal->serialize();
    }

    /**
     * Erzeugt eine Beschreibung mit Themen für den iCal-Anhang.
     */
    private function buildDescription(): string
    {
        $lines = [];
        foreach ($this->meeting->themes as $theme) {
            $lines[] = '- ' . $theme->theme . ' (' . $theme->duration . ' min)';
        }
        $desc = empty($lines) ? 'Keine Themen festgelegt.' : implode("\n", $lines);
        if ($this->messageText) {
            $desc .= "\n\n" . $this->messageText;
        }
        return $desc;
    }
}

