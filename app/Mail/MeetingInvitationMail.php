<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Sabre\VObject\Component\VCalendar;
use Symfony\Component\Mime\Part\DataPart;

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
        $icalString = $this->buildIcal();

        $mail = $this->subject('Einladung zum Meeting: ' . $this->meeting->title)
            ->view('mails.meeting_invitation');

        // ICS als regulären Attachment anhängen (für Clients die den
        // alternativen Part nicht unterstützen)
        $mail->attachData(
            $icalString,
            'einladung.ics',
            ['mime' => 'application/ics']
        );

        // ICS zusätzlich als text/calendar Alternative-Part einbetten,
        // damit Outlook & Co. die Einladung direkt als Kalender-Event erkennen.
        $mail->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message) use ($icalString) {
            $calendarPart = new DataPart(
                $icalString,
                'einladung.ics',
                'text/calendar; charset=UTF-8; method=REQUEST'
            );
            $message->attachPart($calendarPart);
        });

        return $mail;
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
        $fromAddr = config('mail.from.address', 'noreply@example.com');
        $fromName = config('mail.from.name', config('app.name', 'MitarbeiterBoard'));

        // Domain für UID aus der konfigurierten App-URL ableiten (kein .local verwenden)
        $appDomain = parse_url(config('app.url', 'https://mitarbeiter.local'), PHP_URL_HOST) ?: 'mitarbeiter.local';

        $vcal = new VCalendar();

        // METHOD:REQUEST ist zwingend nötig, damit Mailserver die ICS als
        // Kalender-Einladung erkennen und nicht als Spam einstufen.
        $vcal->add('METHOD', 'REQUEST');

        $vevent = $vcal->add('VEVENT', [
            'UID'         => 'meeting-' . $this->meeting->id . '@' . $appDomain,
            'DTSTAMP'     => new \DateTime('now', new \DateTimeZone('UTC')),
            'DTSTART'     => $dtstart->toDateTime(),
            'DTEND'       => $dtend->toDateTime(),
            'SUMMARY'     => $this->meeting->title,
            'DESCRIPTION' => strip_tags($this->buildDescription()),
            'STATUS'      => 'CONFIRMED',
            'SEQUENCE'    => 0,
        ]);

        // ORGANIZER mit CN-Parameter (RFC 5545 §3.8.4.3)
        $organizer = $vevent->add('ORGANIZER', 'mailto:' . $fromAddr);
        $organizer['CN'] = $fromName;

        // ATTENDEE mit korrekten Parametern (RFC 5545 §3.8.4.1)
        $attendee = $vevent->add('ATTENDEE', 'mailto:' . $this->user->email);
        $attendee['CN']       = $this->user->name;
        $attendee['ROLE']     = 'REQ-PARTICIPANT';
        $attendee['PARTSTAT'] = 'NEEDS-ACTION';
        $attendee['RSVP']     = 'TRUE';

        // Location aus Meeting-URL der Gruppe, falls vorhanden
        if (!empty($this->group->meeting_url)) {
            $vevent->add('LOCATION', $this->group->meeting_url);
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

