<?php

namespace App\Notifications;

use App\Models\Post;

/**
 * Neue (oder zu bestätigende) Nachricht in einer Gruppe.
 *
 * Ohne Lesebestätigung: Glocke und Push nach persönlicher Einstellung – die Mail kommt
 * weiterhin als abendliche Sammelmail (PostsController::dailyMail).
 * Mit Lesebestätigung: immer sofort über alle verfügbaren Kanäle.
 */
class NachrichtVeroeffentlicht extends Benachrichtigung
{
    public const GRUND_NEU = 'neu';
    public const GRUND_GEAENDERT = 'geaendert';
    public const GRUND_BEITRITT = 'beitritt';
    public const GRUND_ANGEFORDERT = 'angefordert';

    public function __construct(public Post $post, public string $grund = self::GRUND_NEU)
    {
    }

    public function kategorie(): string
    {
        return 'nachrichten';
    }

    public function via(object $notifiable): array
    {
        if (! $this->post->bestaetigung_erforderlich) {
            return array_values(array_diff(parent::via($notifiable), ['mail']));
        }

        return NachrichtKanaele::alle($notifiable);
    }

    public function titel(object $notifiable): string
    {
        if (! $this->post->bestaetigung_erforderlich) {
            return 'Neue Nachricht: '.$this->post->header;
        }

        return match ($this->grund) {
            self::GRUND_GEAENDERT => 'Geänderte Nachricht – bitte erneut bestätigen',
            default => 'Lesebestätigung erbeten: '.$this->post->header,
        };
    }

    public function text(object $notifiable): string
    {
        $gruppen = $this->post->groups->pluck('name')->join(', ');
        $text = $this->post->header.($gruppen !== '' ? ' ('.$gruppen.')' : '');

        if ($this->post->bestaetigung_erforderlich) {
            $frist = NachrichtKanaele::fristFuer($this->post, $notifiable);
            $text .= $frist ? ' – bitte bis '.$frist->format('d.m.Y').' bestätigen' : ' – bitte Kenntnisnahme bestätigen';
        }

        return $text;
    }

    public function zeilen(object $notifiable): array
    {
        $zeilen = [
            ($this->post->author->name ?? 'Jemand').' hat eine Nachricht veröffentlicht: „'.$this->post->header.'“',
            $this->post->kurztext,
        ];

        if ($this->post->bestaetigung_erforderlich) {
            $zeilen[] = $this->text($notifiable);
        }

        return array_values(array_filter($zeilen));
    }

    public function aktionText(): string
    {
        return $this->post->bestaetigung_erforderlich ? 'Nachricht lesen und bestätigen' : 'Nachricht lesen';
    }

    public function url(object $notifiable): ?string
    {
        return $this->post->url();
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['post_id' => $this->post->id, 'grund' => $this->grund];
    }
}
