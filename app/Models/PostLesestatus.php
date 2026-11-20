<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lese- und Bestätigungsstand einer Person für eine Nachricht.
 *
 * gelesen_at sieht nur die Person selbst; bestaetigt_at/frist bilden die Bestätigungsliste.
 */
class PostLesestatus extends Model
{
    protected $table = 'post_lesestatus';

    protected $fillable = [
        'post_id', 'user_id', 'gelesen_at', 'bestaetigt_at', 'zugewiesen_at', 'frist',
        'erinnert_vorab_at', 'erinnert_frist_at',
    ];

    protected $casts = [
        'gelesen_at' => 'datetime',
        'bestaetigt_at' => 'datetime',
        'zugewiesen_at' => 'datetime',
        'frist' => 'date',
        'erinnert_vorab_at' => 'datetime',
        'erinnert_frist_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function istUeberfaellig(?Carbon $heute = null): bool
    {
        $heute ??= Carbon::today();

        return $this->bestaetigt_at === null && $this->frist !== null && $this->frist->lt($heute);
    }
}
