<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OxSyncLog – Protokolleintrag für CalDAV-Synchronisationsvorgänge.
 *
 * @property int         $id
 * @property int         $ox_calendar_id
 * @property string      $aktion   sync_start|sync_complete|create|update|delete|error
 * @property array|null  $details
 * @property int|null    $user_id
 * @property string|null $ip_adresse
 * @property-read \App\Models\OxCalendar|null $kalender
 * @property-read \App\Models\User|null $benutzer
 */
class OxSyncLog extends Model
{
    use HasFactory;
    protected $table = 'ox_sync_log';

    protected $fillable = [
        'ox_calendar_id',
        'aktion',
        'details',
        'user_id',
        'ip_adresse',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    // --- Relationen ---

    public function kalender(): BelongsTo
    {
        return $this->belongsTo(OxCalendar::class, 'ox_calendar_id');
    }

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


