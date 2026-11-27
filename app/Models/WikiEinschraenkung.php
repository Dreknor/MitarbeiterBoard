<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Freigabe einer eingeschränkten Wiki-Seite für eine Spatie-Rolle oder Gruppe.
 * Hat eine Seite (oder eine übergeordnete Seite) Einträge, sehen sie nur diese Rollen/Gruppen und `manage wiki`.
 */
class WikiEinschraenkung extends Model
{
    protected $table = 'wiki_einschraenkungen';

    protected $fillable = ['seite_id', 'berechtigter_type', 'berechtigter_id'];

    public function seite(): BelongsTo
    {
        return $this->belongsTo(WikiSeite::class, 'seite_id');
    }

    public function berechtigter(): MorphTo
    {
        return $this->morphTo();
    }
}
