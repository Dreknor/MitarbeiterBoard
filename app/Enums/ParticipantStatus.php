<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case Angemeldet      = 'angemeldet';
    case Bestaetigt      = 'bestaetigt';
    case Teilgenommen    = 'teilgenommen';
    case Abgesagt        = 'abgesagt';
    case NichtErschienen = 'nicht_erschienen';

    public function label(): string
    {
        return match ($this) {
            self::Angemeldet      => 'Angemeldet',
            self::Bestaetigt      => 'Bestätigt',
            self::Teilgenommen    => 'Teilgenommen',
            self::Abgesagt        => 'Abgesagt',
            self::NichtErschienen => 'Nicht erschienen',
        };
    }
}

