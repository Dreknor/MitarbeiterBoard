<?php

namespace App\Enums;

enum RetentionStatus: string
{
    case Ausstehend = 'ausstehend';
    case Erinnert   = 'erinnert';
    case Geprueft   = 'geprueft';
    case Behalten   = 'behalten';
    case Geloescht  = 'geloescht';

    public function label(): string
    {
        return match ($this) {
            self::Ausstehend => 'Ausstehend',
            self::Erinnert   => 'Erinnert',
            self::Geprueft   => 'Geprüft',
            self::Behalten   => 'Behalten',
            self::Geloescht  => 'Gelöscht',
        };
    }
}

