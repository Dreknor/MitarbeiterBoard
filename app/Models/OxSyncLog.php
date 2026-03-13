<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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


