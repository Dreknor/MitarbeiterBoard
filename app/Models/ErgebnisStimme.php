<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Punkte einer Person auf einer Karte bei der Abstimmung eines Boards („Punkte kleben“).
 * Regeln in ErgebnisboardAbstimmungService.
 */
class ErgebnisStimme extends Model
{
    protected $table = 'ergebnis_karten_stimmen';

    protected $fillable = ['board_id', 'karte_id', 'user_id', 'anzahl'];

    protected $casts = ['anzahl' => 'integer'];

    /** Nur nicht gelöschte Karten (SoftDeletes-Scope der Karte). */
    public function karte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ErgebnisKarte::class, 'karte_id');
    }
}
