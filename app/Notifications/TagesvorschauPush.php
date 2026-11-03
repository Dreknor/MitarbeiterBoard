<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Kurzfassung der Tagesübersicht als Push – bewusst ohne Eintrag in der Glocke,
 * damit dort nicht jeden Tag eine Übersicht liegt.
 */
class TagesvorschauPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Carbon $zieltag,
        public bool $vorabend,
        public string $kurzfassung,
    ) {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->vorabend ? 'Dein Tag morgen' : 'Dein Tag heute')
            ->body($this->kurzfassung)
            ->icon(asset('img/'.config('config.logo_small')))
            ->tag('tagesvorschau')
            ->data(['url' => route('benachrichtigungen.tag', $this->zieltag->toDateString())]);
    }
}
