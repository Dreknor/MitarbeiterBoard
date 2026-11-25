<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Karte auf einem Ergebnisboard. Anhänge liegen auf der privaten Disk `ergebnisboards`
 * und werden nur über ErgebnisboardController::datei ausgeliefert.
 * Gelöschte Karten sind soft-gelöscht (Anhänge und Kommentare bleiben) und werden nach
 * AUFBEWAHRUNG_TAGE endgültig entfernt (ErgebnisboardService::geloeschteKartenBereinigen()).
 */
class ErgebnisKarte extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'ergebnis_karten';

    public const ANHAENGE = 'anhaenge';

    public const AUFBEWAHRUNG_TAGE = 90;

    protected $fillable = [
        'board_id', 'bereich', 'titel', 'zusatz', 'etikett', 'fokus', 'breit', 'sort', 'faellig_am', 'task_id', 'erinnert_am',
    ];

    protected $casts = [
        'fokus' => 'boolean',
        'breit' => 'boolean',
        'sort' => 'integer',
        'faellig_am' => 'date',
        'erinnert_am' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ANHAENGE)->useDisk('ergebnisboards');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Ergebnisboard::class, 'board_id');
    }

    public function zustaendige(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ergebnis_karte_user', 'karte_id', 'user_id')->withTimestamps()->orderBy('name');
    }

    public function kommentare(): HasMany
    {
        return $this->hasMany(ErgebnisKommentar::class, 'karte_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withoutGlobalScope('active');
    }
}
