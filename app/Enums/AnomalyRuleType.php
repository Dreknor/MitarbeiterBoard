<?php

namespace App\Enums;

/**
 * Regel-Typen der automatisierten Prüfengine für Zeiterfassung, Dienstpläne
 * und Vertragsänderungen (siehe Konzept "Automatisierte Prüfengine").
 */
enum AnomalyRuleType: string
{
    case ContractChangeInPeriod    = 'CONTRACT_CHANGE_IN_PERIOD';
    case RetroactiveContractChange = 'RETROACTIVE_CONTRACT_CHANGE';
    case OverlapCheck               = 'OVERLAP_CHECK';
    case RosterDeviation             = 'ROSTER_DEVIATION';
    case VacationConflict           = 'VACATION_CONFLICT';
    case MissingClockOut            = 'MISSING_CLOCK_OUT';

    public function label(): string
    {
        return match ($this) {
            self::ContractChangeInPeriod    => 'Vertragsänderung im Prüfzeitraum',
            self::RetroactiveContractChange => 'Rückwirkende Vertragsänderung',
            self::OverlapCheck               => 'Überschneidung von Zeitbuchungen',
            self::RosterDeviation             => 'Dienstplan-Abweichung',
            self::VacationConflict           => 'Urlaubs-Konflikt',
            self::MissingClockOut            => 'Fehlzeit / fehlender Stempel-Ausstieg',
        };
    }

    /**
     * Standard-Schweregrad gemäß Prüfregelwerk (kann pro Einzelfall überschrieben werden).
     */
    public function defaultSeverity(): AnomalySeverity
    {
        return match ($this) {
            self::ContractChangeInPeriod    => AnomalySeverity::Warning,
            self::RetroactiveContractChange => AnomalySeverity::High,
            self::OverlapCheck               => AnomalySeverity::Critical,
            self::RosterDeviation             => AnomalySeverity::Warning,
            self::VacationConflict           => AnomalySeverity::High,
            self::MissingClockOut            => AnomalySeverity::High,
        };
    }
}

