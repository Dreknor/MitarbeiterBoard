<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class Push extends Notification
{
    use Queueable;

    public $body;
    public $title;

    public function __construct($title, $body)
    {
        $this->body = $body;
        $this->title = $title;
    }

    public function via($notifiable)
    {
        return [WebPushChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'created' => Carbon::now()->toIso8601String(),
        ];
    }

    public function toWebPush($notifiable, $notification)
    {
        $push = new WebPushMessage;
        $push->title($this->title)
            ->icon(asset('img/'.config('config.logo_small')))
            ->body($this->body);

        return $push;
    }
}
