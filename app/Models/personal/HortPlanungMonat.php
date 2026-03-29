<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class HortPlanungMonat extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\personal\HortPlanungMonatFactory
    {
        return \Database\Factories\personal\HortPlanungMonatFactory::new();
    }

    protected $table = 'hort_planung_monate';

    protected $fillable = [
        'hort_planung_id',
        'monat',
        'kinderanzahl',
        'vollzeitstunden',
        'notiz',
    ];

    protected $casts = [
        'monat'           => 'date:Y-m-d',
        'kinderanzahl'    => 'integer',
        'vollzeitstunden' => 'float',
    ];

    // ── Beziehungen ────────────────────────────────────────────────

    public function planung(): BelongsTo
    {
        return $this->belongsTo(HortPlanung::class, 'hort_planung_id');
    }

    public function personen(): HasMany
    {
        return $this->hasMany(HortPlanungPerson::class, 'hort_planung_monat_id');
    }

    public function monatZusatzstunden(): HasMany
    {
        return $this->hasMany(HortMonatZusatz::class, 'hort_planung_monat_id');
    }

    // ── Hilfsmethoden ──────────────────────────────────────────────

    /**
     * Liefert alle Zusatzstunden mit Typ-Details als Collection.
     * Geeignet für die Verwendung in Berechnungen.
     *
     *
     */
    public function zusatzstunden_details(): Collection
    {
        if ($this->relationLoaded('monatZusatzstunden')) {
            return $this->monatZusatzstunden
                ->filter(fn($z) => $z->typ !== null)
                ->map(fn($z) => [
                    'kuerzel'     => $z->typ->kuerzel,
                    'bezeichnung' => $z->typ->bezeichnung,
                    'stunden'     => $z->stunden,
                    'notiz'       => $z->notiz,
                ]);
        }

        return $this->monatZusatzstunden()
            ->with('typ')
            ->get()
            ->filter(fn($z) => $z->typ !== null)
            ->map(fn($z) => [
                'kuerzel'     => $z->typ->kuerzel,
                'bezeichnung' => $z->typ->bezeichnung,
                'stunden'     => $z->stunden,
                'notiz'       => $z->notiz,
            ]);
    }

    /**
     * Summe aller Zusatzstunden dieses Monats.
     */
    public function summeZusatzstunden(): float
    {
        return (float) $this->monatZusatzstunden()->sum('stunden');
    }
}

