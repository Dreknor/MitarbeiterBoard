<?php

namespace App\Models\Arbeitsblaetter;

use Illuminate\Database\Eloquent\Model;

/** Verbrauch der KI-Erkennung je Aufruf (Grundlage für das Monatslimit). */
class AbKiVerbrauch extends Model
{
    protected $table = 'ab_ki_verbrauch';

    protected $fillable = ['monat', 'blatt_id', 'user_id', 'seiten', 'kosten_cent', 'anbieter'];

    protected $casts = [
        'seiten' => 'integer',
        'kosten_cent' => 'float',
    ];
}
