<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Benachrichtigungs-Einstellung einer Person für eine Kategorie
 * (siehe config/benachrichtigungen.php).
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'kategorie', 'push', 'mail'];

    protected $casts = [
        'push' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
