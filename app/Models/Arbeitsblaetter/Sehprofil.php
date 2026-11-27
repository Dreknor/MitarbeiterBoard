<?php

namespace App\Models\Arbeitsblaetter;

use App\Models\Schueler;
use App\Models\User;
use App\Support\AbProfil;
use Illuminate\Database\Eloquent\Model;

/**
 * Sehprofil eines Kindes: Vorlage + Abweichungen (`werte` enthält nur Abweichungen).
 * Keine Diagnose, kein Visus. Pflege über {@see \App\Services\Arbeitsblaetter\SehprofilService}.
 */
class Sehprofil extends Model
{
    protected $table = 'ab_sehprofile';

    protected $fillable = ['schueler_id', 'vorlage_id', 'werte', 'aktiv', 'geaendert_von'];

    protected $casts = [
        'werte' => 'array',
        'aktiv' => 'boolean',
    ];

    protected $attributes = ['aktiv' => true];

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function vorlage()
    {
        return $this->belongsTo(AbProfilVorlage::class, 'vorlage_id');
    }

    public function bearbeiter()
    {
        return $this->belongsTo(User::class, 'geaendert_von');
    }

    public function scopeAktiv($query)
    {
        return $query->where('aktiv', true);
    }

    /** Vorlage + Abweichungen. */
    public function wirksameWerte(): array
    {
        $basis = $this->vorlage?->wirksameWerte() ?? AbProfil::standard();

        return AbProfil::normalisieren($this->werte ?? [], $basis);
    }

    public function profilHash(): string
    {
        return AbProfil::hash($this->wirksameWerte());
    }
}
