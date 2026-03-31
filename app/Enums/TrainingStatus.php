<?php

namespace App\Enums;

enum TrainingStatus: string
{
    case Geplant       = 'geplant';
    case Bestaetigt    = 'bestaetigt';
    case Durchgefuehrt = 'durchgefuehrt';
    case Abgesagt      = 'abgesagt';

    public function label(): string
    {
        return match ($this) {
            self::Geplant       => 'Geplant',
            self::Bestaetigt    => 'Bestätigt',
            self::Durchgefuehrt => 'Durchgeführt',
            self::Abgesagt      => 'Abgesagt',
        };
    }
}

