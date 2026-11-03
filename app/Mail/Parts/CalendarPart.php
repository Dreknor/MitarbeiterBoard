<?php

namespace App\Mail\Parts;

use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Inline-Kalenderteil einer iMIP-Einladung (text/calendar; method=REQUEST).
 * Der method-Parameter im Content-Type ist laut RFC 6047 Pflicht, damit
 * Mailserver und Clients die Mail als Einladung erkennen.
 */
class CalendarPart extends TextPart
{
    public function __construct(string $ical, private string $method = 'REQUEST')
    {
        // base64, damit Quoted-Printable die iCal-Zeilenfaltung nicht zerlegt
        parent::__construct($ical, 'utf-8', 'calendar', 'base64');
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();
        $headers->setHeaderParameter('Content-Type', 'method', $this->method);

        return $headers;
    }
}
