<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alter Slug (Umbenennen/Verschieben, URLs des alten Wikis) → Seite. */
class WikiWeiterleitung extends Model
{
    protected $table = 'wiki_weiterleitungen';

    protected $fillable = ['alter_slug', 'seite_id'];

    public function seite(): BelongsTo
    {
        return $this->belongsTo(WikiSeite::class, 'seite_id');
    }
}
