<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\PostLesestatus;
use App\Models\User;
use App\Notifications\Channels\BenachrichtigungDatabaseChannel;
use Carbon\Carbon;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Hilfen für Nachrichten-Benachrichtigungen mit Lesebestätigung.
 */
final class NachrichtKanaele
{
    /**
     * Alle verfügbaren Kanäle unabhängig von der persönlichen Einstellung
     * (Lesebestätigungen sollen sicher ankommen).
     *
     * @return array<int, string>
     */
    public static function alle(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $kanaele = [BenachrichtigungDatabaseChannel::class];

        if ($notifiable->pushSubscriptions()->exists()) {
            $kanaele[] = WebPushChannel::class;
        }

        if (filled($notifiable->email)) {
            $kanaele[] = 'mail';
        }

        return $kanaele;
    }

    public static function fristFuer(Post $post, object $notifiable): ?Carbon
    {
        if ($notifiable instanceof User) {
            $frist = PostLesestatus::where('post_id', $post->id)->where('user_id', $notifiable->id)->value('frist');
            if ($frist !== null) {
                return Carbon::parse($frist);
            }
        }

        return $post->bestaetigung_frist;
    }
}
