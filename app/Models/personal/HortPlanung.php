<?php

namespace App\Models\personal;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HortPlanung extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): \Database\Factories\personal\HortPlanungFactory
    {
        return \Database\Factories\personal\HortPlanungFactory::new();
    }

    protected $table = 'hort_planungen';

    protected $fillable = [
        'name',
        'beschreibung',
        'department_id',
        'start_monat',
        'end_monat',
        'typ',
        'aktiv',
        'kopiert_von_id',
        'created_by',
    ];

    protected $casts = [
        'start_monat' => 'date:Y-m-d',
        'end_monat'   => 'date:Y-m-d',
        'aktiv'       => 'boolean',
    ];

    // ── Beziehungen ────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'department_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quell-Planung, von der diese Planung kopiert wurde (nullable).
     */
    public function kopiertvon(): BelongsTo
    {
        return $this->belongsTo(HortPlanung::class, 'kopiert_von_id');
    }

    /**
     * Planungen, die aus dieser Planung kopiert wurden.
     */
    public function kopien(): HasMany
    {
        return $this->hasMany(HortPlanung::class, 'kopiert_von_id');
    }

    public function faktoren(): HasMany
    {
        return $this->hasMany(HortFaktor::class, 'hort_planung_id');
    }

    public function zusatzstundenTypen(): HasMany
    {
        return $this->hasMany(HortZusatzstundenTyp::class, 'hort_planung_id');
    }

    public function monate(): HasMany
    {
        return $this->hasMany(HortPlanungMonat::class, 'hort_planung_id')->orderBy('monat');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(HortPlanungSnapshot::class, 'hort_planung_id');
    }
}

