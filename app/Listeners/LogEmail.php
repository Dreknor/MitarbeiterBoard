<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogEmail
{
    public function __construct()
    {
    }

    /**
     * Event-Zuordnung: Dieser Listener behandelt beide Mail-Events.
     */
    public function subscribe($events): array
    {
        return [
            MessageSending::class => 'handleSending',
            MessageSent::class    => 'handleSent',
        ];
    }

    /**
     * Wird BEVOR die Mail an den SMTP-Server übergeben wird aufgerufen.
     * Achtung: Wenn dieser Handler false zurückgibt, wird der Versand abgebrochen.
     */
    public function handleSending(MessageSending $event): void
    {
        if (! $this->isLoggingEnabled()) {
            return;
        }

        $message = $event->message;
        $to = $message->getTo()[0] ?? null;

        Log::info('Mail wird gesendet', [
            'to'      => $to ? $to->getAddress() : 'unbekannt',
            'name'    => $to ? $to->getName() : '',
            'subject' => $message->getSubject(),
        ]);
    }

    /**
     * Wird NACHDEM die Mail erfolgreich an den SMTP-Server übergeben wurde aufgerufen.
     * Dieses Event bestätigt, dass der SMTP-Server die Mail akzeptiert hat (250 OK).
     */
    public function handleSent(MessageSent $event): void
    {
        if (! $this->isLoggingEnabled()) {
            return;
        }

        $message = $event->message;
        $to = $message->getTo()[0] ?? null;

        // Debug-ID aus dem SMTP-Response extrahieren (falls vorhanden)
        $messageId = $message->getHeaders()->has('Message-ID')
            ? $message->getHeaders()->get('Message-ID')->getBodyAsString()
            : null;

        Log::info('Mail erfolgreich versendet (SMTP bestätigt)', [
            'to'         => $to ? $to->getAddress() : 'unbekannt',
            'name'       => $to ? $to->getName() : '',
            'subject'    => $message->getSubject(),
            'message_id' => $messageId,
        ]);
    }

    /**
     * Prüft ob Mail-Logging in den Settings aktiviert ist.
     */
    private function isLoggingEnabled(): bool
    {
        $setting = Cache::remember('email_setting', 360, function () {
            return DB::table('settings')->where('setting', 'mail_log')->value('value');
        });

        return $setting == '1';
    }
}
