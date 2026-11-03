<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Test-Push aus den Benachrichtigungs-Einstellungen – direkt an alle Geräte,
 * unabhängig von Kategorie-Einstellungen und ohne Eintrag in der Glocke.
 */
class PushTest extends Notification
{
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Test-Benachrichtigung')
            ->body('Push funktioniert auf diesem Gerät. 🎉')
            ->icon(asset('img/'.config('config.logo_small')))
            ->tag('test')
            ->data(['url' => route('benachrichtigungen.einstellungen')]);
    }
}
