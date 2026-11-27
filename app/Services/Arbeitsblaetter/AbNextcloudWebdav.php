<?php

namespace App\Services\Arbeitsblaetter;

use App\Services\Wochenplan\Nextcloud\WpNextcloudWebdav;

/**
 * WebDAV über das Dienstkonto aus `config('arbeitsblaetter.nextcloud')` (Standard: Konto des
 * Wochenplans). Kein eigener Basisordner – der Ordner kommt aus `ab_ordner_vorlage`.
 */
class AbNextcloudWebdav extends WpNextcloudWebdav implements AbNextcloudSpeicher
{
    public function aktiv(): bool
    {
        return (bool) (config('arbeitsblaetter.nextcloud.url') && config('arbeitsblaetter.nextcloud.username'));
    }

    protected function konfigSchluessel(): string
    {
        return 'arbeitsblaetter.nextcloud';
    }

    protected function basisordner(): string
    {
        return '';
    }
}
