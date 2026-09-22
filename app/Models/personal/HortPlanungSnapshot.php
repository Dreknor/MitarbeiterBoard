<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HortPlanungSnapshot extends Model
{
    use HasFactory;

    protected $table = 'hort_planung_snapshots';

    protected $fillable = [
        'hort_planung_id',
        'name',
        'daten',
        'created_by',
    ];

    protected $casts = [
        'daten' => 'array',
    ];

    // ── Beziehungen ────────────────────────────────────────────────

    public function planung(): BelongsTo
    {
        return $this->belongsTo(HortPlanung::class, 'hort_planung_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

