<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WikiTag extends Model
{
    protected $table = 'wiki_tags';

    protected $fillable = ['name', 'slug'];

    public function seiten(): BelongsToMany
    {
        return $this->belongsToMany(WikiSeite::class, 'wiki_seite_tag', 'tag_id', 'seite_id');
    }
}
