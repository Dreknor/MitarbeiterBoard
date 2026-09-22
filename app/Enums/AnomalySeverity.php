<?php

namespace App\Enums;

/**
 * Schweregrad einer durch die Prüfengine (TimeValidationService) erkannten Auffälligkeit.
 */
enum AnomalySeverity: string
{
    case Info     = 'INFO';
    case Warning  = 'WARNING';
    case High     = 'HIGH';
    case Critical = 'CRITICAL';

    public function label(): string
    {
        return match ($this) {
            self::Info     => 'Info',
            self::Warning  => 'Warnung',
            self::High     => 'Wichtig',
            self::Critical => 'Kritisch',
        };
    }

    /**
     * Tailwind-Klassen für Badges / Banner zur Farbcodierung im Frontend.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Info     => 'bg-blue-50 text-blue-700 border border-blue-200',
            self::Warning  => 'bg-yellow-50 text-yellow-800 border border-yellow-200',
            self::High     => 'bg-orange-50 text-orange-800 border border-orange-200',
            self::Critical => 'bg-red-50 text-red-800 border border-red-200',
        };
    }

    /**
     * Tailwind-Klasse zur Hintergrund-Kennzeichnung eines betroffenen Tages in der Monatsansicht.
     */
    public function dayClasses(): string
    {
        return match ($this) {
            self::Info     => 'bg-blue-100',
            self::Warning  => 'bg-yellow-100',
            self::High     => 'bg-orange-100',
            self::Critical => 'bg-red-200',
        };
    }

    /**
     * Sortiergewicht (höher = dringlicher) – nützlich für Sortierung/Aggregation.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Info     => 0,
            self::Warning  => 1,
            self::High     => 2,
            self::Critical => 3,
        };
    }
}

