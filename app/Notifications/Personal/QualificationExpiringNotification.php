<?php

namespace App\Notifications\Personal;

use App\Models\personal\EmployeeQualification;
use App\Notifications\Benachrichtigung;

class QualificationExpiringNotification extends Benachrichtigung
{
    public function __construct(
        private readonly EmployeeQualification $qualification
    ) {}

    public function kategorie(): string
    {
        return 'personal';
    }

    public function titel(object $notifiable): string
    {
        return 'Qualifikation läuft ab: ' . $this->qualification->qualificationType->name;
    }

    public function text(object $notifiable): string
    {
        return "„{$this->qualification->qualificationType->name}“ von {$this->qualification->employe->name} läuft am "
            . $this->qualification->expiry_date?->format('d.m.Y') . ' ab.';
    }

    public function zeilen(object $notifiable): array
    {
        $daysLeft = now()->diffInDays($this->qualification->expiry_date, false);

        return [
            "Die Qualifikation „{$this->qualification->qualificationType->name}“ von {$this->qualification->employe->name} läuft in {$daysLeft} Tagen ab.",
            'Ablaufdatum: ' . $this->qualification->expiry_date?->format('d.m.Y'),
            'Bitte veranlassen Sie eine Erneuerung rechtzeitig.',
        ];
    }

    public function url(object $notifiable): ?string
    {
        return route('personal.qualifications.index', $this->qualification->employe_id);
    }

    public function aktionText(): string
    {
        return 'Qualifikationen anzeigen';
    }

    public function zusatzdaten(object $notifiable): array
    {
        return [
            'type'               => 'qualification_expiring',
            'qualification_id'   => $this->qualification->id,
            'qualification_name' => $this->qualification->qualificationType->name,
            'employe_id'         => $this->qualification->employe_id,
            'expiry_date'        => $this->qualification->expiry_date?->format('Y-m-d'),
        ];
    }
}
