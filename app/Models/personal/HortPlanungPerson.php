<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HortPlanungPerson extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\personal\HortPlanungPersonFactory
    {
        return \Database\Factories\personal\HortPlanungPersonFactory::new();
    }

    protected $table = 'hort_planung_personen';

    protected $fillable = [
        'hort_planung_monat_id',
        'user_id',
        'stunden_gesamt',
        'stunden_stadt',
        'stunden_vertrag',
        'stunden_ist',
        'kommentar',
    ];

    protected $casts = [
        'stunden_gesamt'  => 'float',
        'stunden_stadt'   => 'float',
        'stunden_vertrag' => 'float',
        'stunden_ist'     => 'float',
    ];

    // ── Beziehungen ────────────────────────────────────────────────

    public function monat(): BelongsTo
    {
        return $this->belongsTo(HortPlanungMonat::class, 'hort_planung_monat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

