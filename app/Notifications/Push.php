<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Einfache Push-Benachrichtigung ohne eigene Mail.
 *
 * Landet immer in der Glocke, als Push nur wenn die Person Push für die
 * Kategorie aktiviert und ein Gerät registriert hat. Für neue Ereignisse
 * besser eine eigene Unterklasse von Benachrichtigung anlegen.
 */
class Push extends Benachrichtigung
{
    public function __construct(
        public string $title,
        public string $body,
        public string $kategorieSchluessel = 'system',
        public ?string $ziel = null,
    ) {
    }

    public function kategorie(): string
    {
        return $this->kategorieSchluessel;
    }

    public function titel(object $notifiable): string
    {
        return $this->title;
    }

    public function text(object $notifiable): string
    {
        return $this->body;
    }

    public function url(object $notifiable): ?string
    {
        return $this->ziel;
    }

    public function via(object $notifiable): array
    {
        if (!$notifiable instanceof User) {
            return [];
        }

        // Mail-Kanal bewusst ausgelassen – Push ist kein Mail-Ersatz.
        return array_values(array_filter(
            app(BenachrichtigungsService::class)->kanaeleFuer($notifiable, $this->kategorie()),
            fn (string $kanal) => $kanal !== 'mail'
        ));
    }
}
