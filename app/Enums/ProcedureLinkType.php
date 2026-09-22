<?php

namespace App\Enums;

enum ProcedureLinkType: string
{
    case Onboarding  = 'onboarding';
    case Offboarding = 'offboarding';
    case Versetzung  = 'versetzung';

    public function label(): string
    {
        return match ($this) {
            self::Onboarding  => 'Onboarding',
            self::Offboarding => 'Offboarding',
            self::Versetzung  => 'Versetzung',
        };
    }
}

