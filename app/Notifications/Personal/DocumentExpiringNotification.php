<?php

namespace App\Notifications\Personal;

use App\Models\personal\PersonalDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PersonalDocument $document
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysLeft = now()->diffInDays($this->document->expiry_date, false);

        return (new MailMessage)
            ->subject('Dokument läuft ab: ' . $this->document->title)
            ->greeting('Hinweis: Ablaufendes Dokument')
            ->line("Das Dokument „{$this->document->title}" von {$this->document->employe->name} läuft in {$daysLeft} Tagen ab.")
            ->line("Ablaufdatum: " . $this->document->expiry_date->format('d.m.Y'))
            ->action('Dokument anzeigen', route('personal.documents.index', $this->document->employe_id))
            ->line('Bitte erneuern Sie das Dokument rechtzeitig.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'document_expiring',
            'document_id' => $this->document->id,
            'title'       => $this->document->title,
            'employe_id'  => $this->document->employe_id,
            'expiry_date' => $this->document->expiry_date->format('Y-m-d'),
        ];
    }
}

