<?php

namespace App\Models\Arbeitsblaetter;

use App\Support\AbProfil;
use Illuminate\Database\Eloquent\Model;

/** Profil-Vorlage („Groß“, „Sehr groß“ …) mit vollständigen Werten. */
class AbProfilVorlage extends Model
{
    protected $table = 'ab_profil_vorlagen';

    protected $fillable = ['name', 'werte', 'sortierung'];

    protected $casts = [
        'werte' => 'array',
        'sortierung' => 'integer',
    ];

    public function sehprofile()
    {
        return $this->hasMany(Sehprofil::class, 'vorlage_id');
    }

    /** Vollständige Werte der Vorlage. */
    public function wirksameWerte(): array
    {
        return AbProfil::normalisieren($this->werte ?? []);
    }
}
