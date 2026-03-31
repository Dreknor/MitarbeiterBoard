<?php

namespace App\Enums;

enum BemStatus: string
{
    case Erkannt                = 'erkannt';
    case EinladungVersendet     = 'einladung_versendet';
    case GespraechGeplant       = 'gespraech_geplant';
    case GespraechDurchgefuehrt = 'gespraech_durchgefuehrt';
    case MassnahmenVereinbart   = 'massnahmen_vereinbart';
    case Abgeschlossen          = 'abgeschlossen';
    case AbgelehntDurchMa       = 'abgelehnt_durch_ma';

    public function label(): string
    {
        return match ($this) {
            self::Erkannt                => 'Erkannt',
            self::EinladungVersendet     => 'Einladung versendet',
            self::GespraechGeplant       => 'Gespräch geplant',
            self::GespraechDurchgefuehrt => 'Gespräch durchgeführt',
            self::MassnahmenVereinbart   => 'Maßnahmen vereinbart',
            self::Abgeschlossen          => 'Abgeschlossen',
            self::AbgelehntDurchMa       => 'Abgelehnt durch Mitarbeiter',
        };
    }
}

