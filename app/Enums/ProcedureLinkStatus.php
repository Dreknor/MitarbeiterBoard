<?php

namespace App\Enums;

enum ProcedureLinkStatus: string
{
    case Aktiv         = 'aktiv';
    case Abgeschlossen = 'abgeschlossen';
    case Abgebrochen   = 'abgebrochen';

    public function label(): string
    {
        return match ($this) {
            self::Aktiv         => 'Aktiv',
            self::Abgeschlossen => 'Abgeschlossen',
            self::Abgebrochen   => 'Abgebrochen',
        };
    }
}

