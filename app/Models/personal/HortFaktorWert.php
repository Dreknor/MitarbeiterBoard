<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HortFaktorWert extends Model
{
    use HasFactory;

    protected $table = 'hort_faktor_werte';

    protected $fillable = [
        'hort_faktor_id',
        'wert',
        'gueltig_ab',
        'notiz',
        'created_by',
    ];

    protected $casts = [
        'gueltig_ab' => 'date:Y-m-d',
        'wert'       => 'float',
    ];

    // ── Beziehungen ────────────────────────────────────────────────

    public function faktor(): BelongsTo
    {
        return $this->belongsTo(HortFaktor::class, 'hort_faktor_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

