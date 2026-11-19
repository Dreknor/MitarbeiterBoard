<?php

namespace App\Notifications;

use App\Models\Post;

/**
 * Erinnerung an eine noch offene Lesebestätigung (automatisch vor/an der Frist oder manuell).
 * Immer sofort über alle verfügbaren Kanäle.
 */
class NachrichtErinnerung extends Benachrichtigung
{
    public function __construct(public Post $post, public bool $fristtag = false)
    {
    }

    public function kategorie(): string
    {
        return 'nachrichten';
    }

    public function via(object $notifiable): array
    {
        return NachrichtKanaele::alle($notifiable);
    }

    public function titel(object $notifiable): string
    {
        return 'Erinnerung: Lesebestätigung offen';
    }

    public function text(object $notifiable): string
    {
        $frist = NachrichtKanaele::fristFuer($this->post, $notifiable);

        if ($frist === null) {
            return 'Bitte bestätigen Sie die Nachricht „'.$this->post->header.'“.';
        }

        return $frist->isToday()
            ? 'Bitte bestätigen Sie heute die Nachricht „'.$this->post->header.'“.'
            : 'Bitte bestätigen Sie die Nachricht „'.$this->post->header.'“ bis '.$frist->format('d.m.Y').'.';
    }

    public function aktionText(): string
    {
        return 'Nachricht lesen und bestätigen';
    }

    public function url(object $notifiable): ?string
    {
        return $this->post->url();
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['post_id' => $this->post->id];
    }
}
