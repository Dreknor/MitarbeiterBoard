<?php

namespace App\Notifications\Personal;

use App\Models\personal\PersonalDocument;
use App\Notifications\Benachrichtigung;

class DocumentExpiringNotification extends Benachrichtigung
{
    public function __construct(
        private readonly PersonalDocument $document
    ) {}

    public function kategorie(): string
    {
        return 'personal';
    }

    public function titel(object $notifiable): string
    {
        return 'Dokument läuft ab: ' . $this->document->title;
    }

    public function text(object $notifiable): string
    {
        return "„{$this->document->title}“ von {$this->document->employe->name} läuft am "
            . $this->document->expiry_date->format('d.m.Y') . ' ab.';
    }

    public function zeilen(object $notifiable): array
    {
        $daysLeft = now()->diffInDays($this->document->expiry_date, false);

        return [
            "Das Dokument „{$this->document->title}“ von {$this->document->employe->name} läuft in {$daysLeft} Tagen ab.",
            'Ablaufdatum: ' . $this->document->expiry_date->format('d.m.Y'),
            'Bitte erneuern Sie das Dokument rechtzeitig.',
        ];
    }

    public function url(object $notifiable): ?string
    {
        return route('personal.documents.index', $this->document->employe_id);
    }

    public function aktionText(): string
    {
        return 'Dokument anzeigen';
    }

    public function zusatzdaten(object $notifiable): array
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
