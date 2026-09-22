<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Aktuell    = 'aktuell';
    case Abgelaufen = 'abgelaufen';
    case Archiviert = 'archiviert';

    public function label(): string
    {
        return match ($this) {
            self::Aktuell    => 'Aktuell',
            self::Abgelaufen => 'Abgelaufen',
            self::Archiviert => 'Archiviert',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Aktuell    => 'green',
            self::Abgelaufen => 'red',
            self::Archiviert => 'gray',
        };
    }
}

