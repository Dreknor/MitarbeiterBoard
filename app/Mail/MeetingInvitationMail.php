<?php

namespace App\Mail;

use App\Mail\Parts\CalendarPart;
use App\Models\Meeting;
use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Sabre\VObject\Component\VCalendar;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;

class MeetingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $meeting;
    public $group;
    public $user;
    public $messageText;
    public $absender;
    public $absenderEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(Meeting $meeting, ?Group $group, User $user, $messageText = null, $absender = null, $absenderEmail = null)
    {
        $this->meeting = $meeting;
        $this->group = $group;
        $this->user = $user;
        $this->messageText = $messageText;
        $this->absender = $absender ?: '';
        $this->absenderEmail = $absenderEmail;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $icalString = $this->buildIcal();

        $mail = $this->subject('Einladung zum Meeting: ' . $this->meeting->title)
            ->view('mails.meeting_invitation');

        // Reply-To auf den tatsächlichen Versender setzen, damit
        // Termin-Bestätigungen nicht an die noreply-Adresse gehen.
        if ($this->absenderEmail) {
            $mail->replyTo($this->absenderEmail, $this->absender);
        }

        // Einladung so aufbauen, wie es Kalender-Clients und Mailserver erwarten
        // (Aufbau wie bei Google/Outlook, RFC 6047):
        //   multipart/mixed
        //     multipart/alternative
        //       text/plain
        //       text/html
        //       text/calendar; method=REQUEST   (inline, nicht als Anhang)
        //     application/ics                   (einladung.ics für einfache Clients)
        // Zwei REQUEST-Teile als Anhang (ohne Inline-Kalenderteil) werden von
        // Empfangsservern teils abgelehnt oder in Quarantäne verschoben.
        $html = view('mails.meeting_invitation', $this->buildViewData())->render();

        $mail->withSymfonyMessage(function (Email $message) use ($html, $icalString) {
            $message->setBody(new MixedPart(
                new AlternativePart(
                    new TextPart($this->buildPlainText($html), 'utf-8', 'plain'),
                    new TextPart($html, 'utf-8', 'html'),
                    new CalendarPart($icalString)
                ),
                new DataPart($icalString, 'einladung.ics', 'application/ics')
            ));
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
        // Zeiten in UTC ausgeben: TZID=Europe/Berlin ohne VTIMEZONE-Block ist
        // laut RFC 5545 ungültig und wird von manchen Servern verworfen.
        $dtstart  = \Carbon\Carbon::parse($date . ' ' . $this->meeting->start_time, $tz)->utc();
        $dtend    = \Carbon\Carbon::parse($date . ' ' . $this->meeting->end_time, $tz)->utc();
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
        // Falls vorhanden, wird die E-Mail des tatsächlichen Versenders genutzt,
        // damit Kalender-Bestätigungen an die richtige Person gehen.
        $organizerAddr = $this->absenderEmail ?: $fromAddr;
        $organizerName = $this->absender ?: $fromName;
        $organizer = $vevent->add('ORGANIZER', 'mailto:' . $organizerAddr);
        $organizer['CN'] = $organizerName;

        // Die Mail kommt von der noreply-Adresse, nicht vom Organisator. Ohne
        // SENT-BY sieht das für Mailserver nach einer gefälschten Einladung aus
        // (iMIP-Absender ≠ ORGANIZER, RFC 6047 §3).
        if (strcasecmp($organizerAddr, $fromAddr) !== 0) {
            $organizer['SENT-BY'] = 'mailto:' . $fromAddr;
        }

        // ATTENDEE mit korrekten Parametern (RFC 5545 §3.8.4.1)
        $attendee = $vevent->add('ATTENDEE', 'mailto:' . $this->user->email);
        $attendee['CN']       = $this->user->name;
        $attendee['ROLE']     = 'REQ-PARTICIPANT';
        $attendee['PARTSTAT'] = 'NEEDS-ACTION';
        $attendee['RSVP']     = 'TRUE';

        $location = $this->buildLocation();
        if (!empty($location)) {
            $vevent->add('LOCATION', $location);
        }

        return $vcal->serialize();
    }

    /**
     * Erzeugt eine Beschreibung mit Themen für den iCal-Anhang.
     */
    private function buildDescription(): string
    {
        $lines = [];

        if ($this->meeting->roomBooking?->room) {
            $room = $this->meeting->roomBooking->room;
            $lines[] = 'Raum: ' . $room->name . ($room->room_number ? ' (Nr. ' . $room->room_number . ')' : '');
        }

        if (!empty($this->meeting->location)) {
            $lines[] = 'Ort: ' . $this->meeting->location;
        }

        if (!empty($this->meeting->effectiveMeetingUrl())) {
            $lines[] = 'Meeting-Link: ' . $this->meeting->effectiveMeetingUrl();
        }

        $lines[] = 'Details: ' . route('meetings.show', $this->meeting);

        foreach ($this->meeting->themes as $theme) {
            $lines[] = '- ' . $theme->theme . ' (' . $theme->duration . ' min)';
        }
        $desc = empty($lines) ? 'Keine Themen festgelegt.' : implode("\n", $lines);
        if ($this->messageText) {
            $desc .= "\n\n" . $this->messageText;
        }
        return $desc;
    }

    private function buildPlainText(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/li|\/ul)\s*\/?>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n\s*\n+/", "\n", preg_replace('/^[ \t]+/m', '', $text)));
    }

    private function buildLocation(): ?string
    {
        $parts = [];

        if ($this->meeting->roomBooking?->room) {
            $room = $this->meeting->roomBooking->room;
            $parts[] = $room->name . ($room->room_number ? ' (Nr. ' . $room->room_number . ')' : '');
        }

        if (!empty($this->meeting->location)) {
            $parts[] = $this->meeting->location;
        }

        if (!empty($this->meeting->effectiveMeetingUrl())) {
            $parts[] = $this->meeting->effectiveMeetingUrl();
        }

        if (empty($parts)) {
            return null;
        }

        return implode(' | ', $parts);
    }
}

