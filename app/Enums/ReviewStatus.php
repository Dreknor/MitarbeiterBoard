<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Geplant       = 'geplant';
    case Durchgefuehrt = 'durchgefuehrt';
    case Verschoben    = 'verschoben';
    case Abgesagt      = 'abgesagt';

    public function label(): string
    {
        return match ($this) {
            self::Geplant       => 'Geplant',
            self::Durchgefuehrt => 'Durchgeführt',
            self::Verschoben    => 'Verschoben',
            self::Abgesagt      => 'Abgesagt',
        };
    }
}

