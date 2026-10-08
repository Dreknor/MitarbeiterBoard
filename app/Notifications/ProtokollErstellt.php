<?php

namespace App\Notifications;

use App\Mail\newProtocolForTask;
use App\Models\Protocol;
use Illuminate\Mail\Mailable;

/**
 * Neues Protokoll zu einem Thema (für Ersteller von Aufgaben-Themen und Abonnenten).
 */
class ProtokollErstellt extends Benachrichtigung
{
    public function __construct(
        public Protocol $protocol,
        public string $autor,
    ) {
    }

    public function kategorie(): string
    {
        return 'themen';
    }

    public function titel(object $notifiable): string
    {
        return 'Neues Protokoll';
    }

    public function text(object $notifiable): string
    {
        return $this->autor.' hat ein Protokoll zum Thema „'.$this->protocol->theme?->theme.'“ geschrieben.';
    }

    public function url(object $notifiable): ?string
    {
        return $this->protocol->theme?->url();
    }

    public function mailable(object $notifiable): ?Mailable
    {
        $theme = $this->protocol->theme;

        // Bisheriges Mail-Template nur für Gruppen-Themen (Link enthält den Gruppennamen)
        if (!$theme?->group) {
            return null;
        }

        return new newProtocolForTask($this->autor, $theme, $theme->group->name, $this->protocol);
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['theme_id' => $this->protocol->theme_id, 'protocol_id' => $this->protocol->id];
    }
}
