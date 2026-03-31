<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Aktiv   = 'aktiv';
    case Ruhend  = 'ruhend';
    case Beendet = 'beendet';

    public function label(): string
    {
        return match ($this) {
            self::Aktiv   => 'Aktiv',
            self::Ruhend  => 'Ruhend',
            self::Beendet => 'Beendet',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Aktiv;
    }
}

