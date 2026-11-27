<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Zugriff auf ein Ergebnisboard: Person (User), Gruppe (Group) oder Rolle mit Recht
 * lesen|schreiben|verwalten (verwalten nur für Personen).
 */
class ErgebnisBoardZugriff extends Model
{
    protected $table = 'ergebnis_board_zugriffe';

    protected $fillable = ['board_id', 'berechtigter_type', 'berechtigter_id', 'recht'];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Ergebnisboard::class, 'board_id');
    }

    public function berechtigter(): MorphTo
    {
        return $this->morphTo();
    }
}
