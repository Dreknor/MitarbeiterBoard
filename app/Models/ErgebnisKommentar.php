<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kommentar auf einem Board (karte_id = null) oder an einer Karte. Reiner Text;
 * `erwaehnungen` = User-IDs der @-Erwähnungen. Regeln in ErgebnisboardKommentarService.
 */
class ErgebnisKommentar extends Model
{
    use SoftDeletes;

    protected $table = 'ergebnis_kommentare';

    public const MAX_ZEICHEN = 5000;

    protected $fillable = ['board_id', 'karte_id', 'user_id', 'text', 'erwaehnungen', 'bearbeitet_at'];

    protected $casts = [
        'erwaehnungen' => 'array',
        'bearbeitet_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Ergebnisboard::class, 'board_id');
    }

    public function karte(): BelongsTo
    {
        return $this->belongsTo(ErgebnisKarte::class, 'karte_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
