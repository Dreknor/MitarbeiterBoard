<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Wie der Laravel-Datenbankkanal, schreibt aber zusätzlich die Kategorie
 * in die Spalte notifications.kategorie (Filter im Verlauf, Zusammenfassung).
 */
class BenachrichtigungDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if (method_exists($notification, 'kategorie')) {
            $payload['kategorie'] = $notification->kategorie();
        }

        return $payload;
    }
}
