<?php

namespace App\Enums;

enum TeacherQualificationLevel: string
{
    case Fakultas              = 'fakultas';
    case FachfremdQualifiziert = 'fachfremd_qualifiziert';
    case Seiteneinsteiger      = 'seiteneinsteiger';
    case Keine                 = 'keine';

    public function label(): string
    {
        return match ($this) {
            self::Fakultas              => 'Volle Lehrbefähigung (Fakultas)',
            self::FachfremdQualifiziert => 'Fachfremd (qualifiziert)',
            self::Seiteneinsteiger      => 'Seiteneinsteiger',
            self::Keine                 => 'Keine Qualifizierung',
        };
    }
}

