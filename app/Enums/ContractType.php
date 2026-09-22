<?php

namespace App\Enums;

enum ContractType: string
{
    case Unbefristet        = 'unbefristet';
    case Befristet          = 'befristet';
    case BefristetSachgrund = 'befristet_sachgrund';

    public function label(): string
    {
        return match ($this) {
            self::Unbefristet        => 'Unbefristet',
            self::Befristet          => 'Befristet (ohne Sachgrund)',
            self::BefristetSachgrund => 'Befristet (mit Sachgrund)',
        };
    }

    public function isBefristet(): bool
    {
        return in_array($this, [self::Befristet, self::BefristetSachgrund]);
    }
}

