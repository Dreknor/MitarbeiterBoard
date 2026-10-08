<?php

namespace App\Notifications;

use Carbon\Carbon;

/**
 * Eine Vertretung der Person wurde angelegt, geändert oder gestrichen.
 *
 * Bewusst mit einfachen Werten statt Model, damit auch gelöschte
 * Vertretungen (Queue) noch gemeldet werden können.
 */
class VertretungGeaendert extends Benachrichtigung
{
    public const NEU = 'neu';
    public const GEAENDERT = 'geaendert';
    public const ENTFAELLT = 'entfaellt';

    public function __construct(
        public string $art,
        public Carbon $datum,
        public string $stunde,
        public string $klasse,
        public ?string $fach,
        public ?string $kommentar = null,
    ) {
    }

    public function kategorie(): string
    {
        return 'vertretungen';
    }

    public function titel(object $notifiable): string
    {
        $tag = $this->datum->isToday() ? 'heute' : ($this->datum->isTomorrow() ? 'morgen' : $this->datum->locale('de')->isoFormat('dd, D.M.'));

        return match ($this->art) {
            self::NEU => 'Neue Vertretung '.$tag,
            self::ENTFAELLT => 'Vertretung '.$tag.' entfällt',
            default => 'Vertretung '.$tag.' geändert',
        };
    }

    public function text(object $notifiable): string
    {
        return trim($this->stunde.'. Std. · '.$this->klasse.($this->fach ? ' · '.$this->fach : '')
            .($this->kommentar ? ' · '.$this->kommentar : ''));
    }

    public function zeilen(object $notifiable): array
    {
        return [
            $this->titel($notifiable).' ('.$this->datum->locale('de')->isoFormat('dddd, D.M.YYYY').'):',
            $this->text($notifiable),
        ];
    }

    public function url(object $notifiable): ?string
    {
        return route('benachrichtigungen.tag', $this->datum->toDateString());
    }

    public function aktionText(): string
    {
        return 'Meinen Tag anzeigen';
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['art' => $this->art, 'datum' => $this->datum->toDateString()];
    }
}
