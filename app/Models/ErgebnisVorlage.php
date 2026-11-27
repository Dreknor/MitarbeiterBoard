<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eigene Vorlage aus dem Vorlagen-Editor. `definition` hat dasselbe Format wie die Einträge
 * in App\Support\ErgebnisboardVorlagen::VORLAGEN (ohne titel/beschreibung).
 */
class ErgebnisVorlage extends Model
{
    protected $table = 'ergebnis_vorlagen';

    protected $fillable = ['schluessel', 'titel', 'beschreibung', 'definition', 'aktiv', 'created_by'];

    protected $casts = [
        'definition' => 'array',
        'aktiv' => 'boolean',
    ];

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
