<?php

namespace App\Enums;

enum QualificationStatus: string
{
    case Gueltig    = 'gueltig';
    case Ablaufend  = 'ablaufend';
    case Abgelaufen = 'abgelaufen';
    case Fehlend    = 'fehlend';

    public function label(): string
    {
        return match ($this) {
            self::Gueltig    => 'Gültig',
            self::Ablaufend  => 'Ablaufend',
            self::Abgelaufen => 'Abgelaufen',
            self::Fehlend    => 'Fehlend',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Gueltig    => 'green',
            self::Ablaufend  => 'yellow',
            self::Abgelaufen => 'red',
            self::Fehlend    => 'gray',
        };
    }
}

