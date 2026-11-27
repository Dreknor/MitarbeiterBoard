<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interner Link einer Wiki-Seite auf einen Slug (Backlinks, rote Links).
 * ziel_seite_id ist gesetzt, sobald es eine Seite mit dem Slug (oder einer Weiterleitung darauf) gibt.
 */
class WikiLink extends Model
{
    public $timestamps = false;

    protected $table = 'wiki_links';

    protected $fillable = ['von_seite_id', 'ziel_slug', 'ziel_seite_id'];

    public function von(): BelongsTo
    {
        return $this->belongsTo(WikiSeite::class, 'von_seite_id');
    }
}
