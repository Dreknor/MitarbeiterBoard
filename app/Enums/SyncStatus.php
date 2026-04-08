<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Synced     = 'synced';
    case Uploading  = 'uploading';
    case SyncFehler = 'sync_fehler';
    case Pending    = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Synced     => 'Synchronisiert',
            self::Uploading  => 'Wird hochgeladen',
            self::SyncFehler => 'Sync-Fehler',
            self::Pending    => 'Ausstehend',
        };
    }
}

