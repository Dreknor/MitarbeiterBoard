<?php

namespace App\Models;

use App\Support\WikiHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine gespeicherte Fassung einer Wiki-Seite (wird nie gelöscht).
 */
class WikiRevision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'wiki_revisionen';

    protected $fillable = ['seite_id', 'titel', 'inhalt', 'notiz', 'user_id', 'wiederhergestellt_aus', 'created_at'];

    public function seite(): BelongsTo
    {
        return $this->belongsTo(WikiSeite::class, 'seite_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'System']);
    }

    public function quelle(): BelongsTo
    {
        return $this->belongsTo(self::class, 'wiederhergestellt_aus');
    }

    public function getInhaltHtmlAttribute(): string
    {
        return WikiHtml::ausgabe($this->inhalt, 'revision-'.$this->id);
    }
}
