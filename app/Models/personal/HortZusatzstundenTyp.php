<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HortZusatzstundenTyp extends Model
{
    use HasFactory;

    protected $table = 'hort_zusatzstunden_typen';

    protected $fillable = [
        'hort_planung_id',
        'kuerzel',
        'bezeichnung',
        'position',
        'aktiv',
    ];

    protected $casts = [
        'aktiv'    => 'boolean',
        'position' => 'integer',
    ];

    // ── Beziehungen ────────────────────────────────────────────────

    public function planung(): BelongsTo
    {
        return $this->belongsTo(HortPlanung::class, 'hort_planung_id');
    }

    public function monatZusatzstunden(): HasMany
    {
        return $this->hasMany(HortMonatZusatz::class, 'hort_zusatzstunden_typ_id');
    }
}

