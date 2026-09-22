<?php

namespace App\Enums;

enum EmploymentType: string
{
    case Regulaer    = 'regulaer';
    case Lehrer      = 'lehrer';
    case Praktikant  = 'praktikant';
    case Ehrenamt    = 'ehrenamt';
    case Minijob     = 'minijob';

    public function label(): string
    {
        return match ($this) {
            self::Regulaer   => 'Reguläre Anstellung',
            self::Lehrer     => 'Lehrkraft',
            self::Praktikant => 'Praktikant/in',
            self::Ehrenamt   => 'Ehrenamt',
            self::Minijob    => 'Minijob',
        };
    }

    public function requiresTeacherDetail(): bool
    {
        return $this === self::Lehrer;
    }
}

