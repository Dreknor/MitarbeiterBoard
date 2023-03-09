<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class WikiSite extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $fillable = ['author_id', 'title', 'text', 'previous_version'];
    protected $visible = ['author_id', 'title', 'text', 'previous_version'];


    protected function getSlugAttribute()
    {
        return str_replace(' ', '-', $this->title);
    }

    public function author()
    {
        return $this->belongsTo(User::class);
    }

    public function original(){
        return $this->belongsTo(WikiSite::class);
    }

    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
            'text' => $this->text,
        ];
    }
}
