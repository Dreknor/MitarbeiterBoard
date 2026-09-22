<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIcalFeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'url', 'farbe', 'aktiv',
        'letzter_abruf', 'fehler_meldung',
    ];

    protected $casts = [
        'aktiv'         => 'boolean',
        'letzter_abruf' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

