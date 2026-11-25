<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eintrag im Änderungsverlauf eines Boards (fachliche Aktion + Details als JSON).
 * Geschrieben ausschließlich von ErgebnisboardVerlaufService; `karte_id` bleibt auch
 * nach dem endgültigen Löschen der Karte stehen (Titel steht in `details`).
 */
class ErgebnisVerlauf extends Model
{
    protected $table = 'ergebnis_board_verlauf';

    public const UPDATED_AT = null;

    protected $fillable = ['board_id', 'karte_id', 'user_id', 'aktion', 'details'];

    protected $casts = [
        'details' => 'array',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Ergebnisboard::class, 'board_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
