<?php

namespace App\Enums;

enum QualificationCategory: string
{
    case Pflicht    = 'pflicht';
    case Empfohlen  = 'empfohlen';
    case Freiwillig = 'freiwillig';

    public function label(): string
    {
        return match ($this) {
            self::Pflicht    => 'Pflicht',
            self::Empfohlen  => 'Empfohlen',
            self::Freiwillig => 'Freiwillig',
        };
    }
}

