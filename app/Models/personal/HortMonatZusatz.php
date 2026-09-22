<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HortMonatZusatz extends Model
{
    use HasFactory;

    protected $table = 'hort_monat_zusatzstunden';

    protected $fillable = [
        'hort_planung_monat_id',
        'hort_zusatzstunden_typ_id',
        'stunden',
        'notiz',
    ];

    protected $casts = [
        'stunden' => 'float',
    ];

    // ── Beziehungen ────────────────────────────────────────────────

    public function monat(): BelongsTo
    {
        return $this->belongsTo(HortPlanungMonat::class, 'hort_planung_monat_id');
    }

    public function typ(): BelongsTo
    {
        return $this->belongsTo(HortZusatzstundenTyp::class, 'hort_zusatzstunden_typ_id');
    }
}

