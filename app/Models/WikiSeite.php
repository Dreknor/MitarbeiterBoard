<?php

namespace App\Models;

use App\Support\WikiHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Wiki-Seite (Seitenbaum über parent_id). Inhalt nur über App\Services\Wiki\WikiService speichern
 * (Revision, Bereinigung, Links, Version) und nur über `inhalt_html` ausgeben.
 */
class WikiSeite extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    /** Slug der Startseite – ihr Inhalt ist die Einleitung auf /wiki, sie erscheint nicht im Baum. */
    public const START_SLUG = 'start';

    public const BILDER = 'bilder';
    public const DATEIEN = 'dateien';

    protected $table = 'wiki_seiten';

    protected $fillable = ['titel', 'slug', 'parent_id', 'sortierung', 'geschuetzt', 'systemseite'];

    protected $casts = [
        'geschuetzt' => 'boolean',
        'systemseite' => 'boolean',
        'version' => 'integer',
        'sortierung' => 'integer',
        'parent_id' => 'integer',
        'bearbeitet_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::BILDER)->useDisk('wiki');
        $this->addMediaCollection(self::DATEIEN)->useDisk('wiki');
    }

    public function eltern(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function kinder(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function revisionen(): HasMany
    {
        return $this->hasMany(WikiRevision::class, 'seite_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(WikiTag::class, 'wiki_seite_tag', 'seite_id', 'tag_id')->orderBy('name');
    }

    public function einschraenkungen(): HasMany
    {
        return $this->hasMany(WikiEinschraenkung::class, 'seite_id');
    }

    public function favorisiertVon(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wiki_favoriten', 'seite_id', 'user_id');
    }

    public function ausgehendeLinks(): HasMany
    {
        return $this->hasMany(WikiLink::class, 'von_seite_id');
    }

    public function erstellt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von')->withDefault(['name' => 'System']);
    }

    public function geaendert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geaendert_von')->withDefault(['name' => 'System']);
    }

    public function istStartseite(): bool
    {
        return $this->slug === self::START_SLUG;
    }

    /** Geschützt = nur `manage wiki` darf bearbeiten (Systemseiten sind immer geschützt). */
    public function istGeschuetzt(): bool
    {
        return $this->geschuetzt || $this->systemseite;
    }

    /** Bereinigtes HTML der aktuellen Fassung (HTMLPurifier, Cache je Seite + Version). */
    public function getInhaltHtmlAttribute(): string
    {
        return WikiHtml::ausgabe($this->inhalt, 'seite-'.$this->id.'-'.$this->version);
    }

    public function url(): string
    {
        return $this->istStartseite() ? route('wiki') : route('wiki.show', $this->slug);
    }
}
