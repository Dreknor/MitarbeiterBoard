<?php

namespace App\Enums;

enum EmploymentStatusReason: string
{
    case Elternzeit    = 'elternzeit';
    case Mutterschutz  = 'mutterschutz';
    case Sabbatical    = 'sabbatical';
    case Langzeitkrank = 'langzeitkrank';
    case Sonstig       = 'sonstig';

    public function label(): string
    {
        return match ($this) {
            self::Elternzeit    => 'Elternzeit',
            self::Mutterschutz  => 'Mutterschutz',
            self::Sabbatical    => 'Sabbatical',
            self::Langzeitkrank => 'Langzeitkrank',
            self::Sonstig       => 'Sonstig',
        };
    }
}

