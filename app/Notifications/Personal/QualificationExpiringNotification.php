<?php

namespace App\Notifications\Personal;

use App\Models\personal\EmployeeQualification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QualificationExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly EmployeeQualification $qualification
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysLeft    = now()->diffInDays($this->qualification->expiry_date, false);
        $qualName    = $this->qualification->qualificationType->name;
        $employeName = $this->qualification->employe->name;

        return (new MailMessage)
            ->subject("Qualifikation läuft ab: {$qualName}")
            ->greeting('Hinweis: Ablaufende Qualifikation')
            ->line("Die Qualifikation „{$qualName}" von {$employeName} läuft in {$daysLeft} Tagen ab.")
            ->line("Ablaufdatum: " . $this->qualification->expiry_date->format('d.m.Y'))
            ->action('Qualifikationen anzeigen', route('personal.qualifications.index', $this->qualification->employe_id))
            ->line('Bitte veranlassen Sie eine Erneuerung rechtzeitig.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                 => 'qualification_expiring',
            'qualification_id'     => $this->qualification->id,
            'qualification_name'   => $this->qualification->qualificationType->name,
            'employe_id'           => $this->qualification->employe_id,
            'expiry_date'          => $this->qualification->expiry_date?->format('Y-m-d'),
        ];
    }
}

