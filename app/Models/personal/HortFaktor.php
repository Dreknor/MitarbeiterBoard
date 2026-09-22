<?php

namespace App\Models\personal;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HortFaktor extends Model
{
    use HasFactory;

    protected $table = 'hort_faktoren';

    protected $fillable = [
        'hort_planung_id',
        'kuerzel',
        'bezeichnung',
        'berechnungs_typ',
        'position',
        'aktiv',
        'gesetzliche_grundlage',
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

    public function werte(): HasMany
    {
        return $this->hasMany(HortFaktorWert::class, 'hort_faktor_id')->orderBy('gueltig_ab');
    }

    // ── Hilfsmethoden ──────────────────────────────────────────────

    /**
     * Ermittelt den zeitlich gültigen Faktor-Wert für einen bestimmten Monat.
     * Der gültige Wert ist der mit dem jüngsten gueltig_ab ≤ Monat.
     *
     * Hinweis: Wenn `werte` bereits eager-geladen ist, wird kein zusätzlicher
     * DB-Query ausgeführt.
     */
    public function wertFuerMonat(Carbon $monat): ?float
    {
        // Eager-Loaded Collection nutzen, falls vorhanden
        if ($this->relationLoaded('werte')) {
            return $this->werte
                ->filter(fn($w) => $w->gueltig_ab->lessThanOrEqualTo($monat))
                ->sortByDesc('gueltig_ab')
                ->first()?->wert;
        }

        return $this->werte()
            ->where('gueltig_ab', '<=', $monat->toDateString())
            ->orderByDesc('gueltig_ab')
            ->first()?->wert;
    }
}

