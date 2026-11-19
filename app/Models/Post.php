<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Nachricht an eine oder mehrere Gruppen (Dashboard-Ankündigung oder ausführliche Gruppen-Nachricht).
 *
 * Alle Änderungen über App\Services\Posts\PostService, Zugriff über PostPolicy.
 * Veröffentlichte Nachrichten werden nur archiviert, gelöscht werden nur Entwürfe.
 */
class Post extends Model implements HasMedia
{
    use HasFactory;
    use SoftDeletes;
    use InteractsWithMedia;

    /** Ungelesene Nachrichten zählen nur so lange als „neu“ (Tage nach Veröffentlichung). */
    public const NEU_TAGE = 14;

    /** Länge der automatischen Kurzfassung auf dem Dashboard. */
    public const KURZ_ZEICHEN = 220;

    protected $fillable = [
        'author_id', 'header', 'kurzfassung', 'text', 'released', 'archived', 'created_at',
        'auf_dashboard', 'sichtbar_bis', 'bestaetigung_erforderlich', 'bestaetigung_frist',
    ];

    protected $casts = [
        'released' => 'boolean',
        'archived' => 'boolean',
        'auf_dashboard' => 'boolean',
        'bestaetigung_erforderlich' => 'boolean',
        'sichtbar_bis' => 'date',
        'bestaetigung_frist' => 'date',
        'bestaetigung_angefordert_at' => 'datetime',
        'manuell_erinnert_at' => 'datetime',
        'veroeffentlicht_at' => 'datetime',
        'archiviert_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id', 'id')->withDefault([
            'name' => 'System / gelöschter Benutzer',
        ]);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function lesestatus(): HasMany
    {
        return $this->hasMany(PostLesestatus::class);
    }

    /**
     * Veröffentlichte, nicht gelöschte Nachrichten in Gruppen, die die Person sieht,
     * plus eigene Entwürfe.
     */
    public function scopeSichtbarFuer(Builder $query, User $user): Builder
    {
        $gruppen = $user->groups()->pluck('id')->all();

        return $query->where(function (Builder $q) use ($user, $gruppen) {
            $q->where(function (Builder $q) use ($gruppen) {
                $q->where('released', true)
                    ->whereHas('groups', fn (Builder $g) => $g->whereIn('groups.id', $gruppen));
            })->orWhere('author_id', $user->id);
        });
    }

    public function scopeVeroeffentlicht(Builder $query): Builder
    {
        return $query->where('released', true);
    }

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('archived', false);
    }

    public function istVeroeffentlicht(): bool
    {
        return (bool) $this->released;
    }

    /** Wurde die Nachricht jemals veröffentlicht? Dann wird sie nur noch archiviert. */
    public function warVeroeffentlicht(): bool
    {
        return $this->released || $this->veroeffentlicht_at !== null;
    }

    /** Bereinigtes HTML des ausführlichen Textes. */
    public function getTextHtmlAttribute(): string
    {
        return HtmlSanitizer::clean($this->text);
    }

    /** Kurzfassung oder automatisch gekürzter Text (ohne HTML). */
    public function getKurztextAttribute(): string
    {
        if (filled($this->kurzfassung)) {
            return (string) $this->kurzfassung;
        }

        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $this->text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return Str::limit($text, self::KURZ_ZEICHEN);
    }

    /** Ist der Text länger als die Kurzfassung (→ „weiterlesen“)? */
    public function hatMehrText(): bool
    {
        if (filled($this->kurzfassung)) {
            return filled(strip_tags((string) $this->text));
        }

        $text = trim(strip_tags((string) $this->text));

        return mb_strlen($text) > self::KURZ_ZEICHEN || $this->getMedia('files')->isNotEmpty() || $this->getMedia('images')->isNotEmpty();
    }

    public function datum(): ?Carbon
    {
        return $this->veroeffentlicht_at ?? $this->created_at;
    }

    public function url(): string
    {
        return route('posts.show', $this);
    }
}
