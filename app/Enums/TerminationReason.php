<?php

namespace App\Enums;

enum TerminationReason: string
{
    case KuendigungAN      = 'kuendigung_an';
    case KuendigungAG      = 'kuendigung_ag';
    case Aufhebung         = 'aufhebung';
    case Befristungsablauf = 'befristungsablauf';
    case Verrentung        = 'verrentung';
    case Sonstig           = 'sonstig';

    public function label(): string
    {
        return match ($this) {
            self::KuendigungAN      => 'Kündigung (Arbeitnehmer)',
            self::KuendigungAG      => 'Kündigung (Arbeitgeber)',
            self::Aufhebung         => 'Aufhebungsvertrag',
            self::Befristungsablauf => 'Befristungsablauf',
            self::Verrentung        => 'Verrentung',
            self::Sonstig           => 'Sonstig',
        };
    }
}

