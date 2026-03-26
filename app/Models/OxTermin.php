<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\OxTerminTeilnehmer;

/**
 * OxTermin – Lokal gecachter Kalender-Termin aus OX.
 *
 * @property int         $id
 * @property int         $ox_calendar_id
 * @property string      $ox_uid             iCal UID
 * @property string|null $ox_etag
 * @property string|null $ox_href
 * @property string      $titel
 * @property string|null $beschreibung
 * @property string|null $ort
 * @property \Carbon\Carbon $beginn
 * @property \Carbon\Carbon $ende
 * @property string|null $timezone
 * @property bool        $ganztaegig
 * @property string|null $rrule
 * @property array|null  $exdates
 * @property string|null $status
 * @property int|null    $erstellt_von
 * @property string|null $raw_ical
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \App\Models\OxCalendar $kalender
 * @property-read \App\Models\User|null $ersteller
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OxTerminTeilnehmer> $teilnehmer
 */
class OxTermin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ox_termine';

    protected $fillable = [
        'ox_calendar_id',
        'ox_uid',
        'ox_etag',
        'ox_href',
        'titel',
        'beschreibung',
        'ort',
        'beginn',
        'ende',
        'timezone',
        'ganztaegig',
        'rrule',
        'exdates',
        'status',
        'erstellt_von',
        'raw_ical',
    ];

    protected $casts = [
        'beginn'     => 'datetime',
        'ende'       => 'datetime',
        'ganztaegig' => 'boolean',
        'exdates'    => 'array',
    ];

    /**
     * Automatisch eine iCal-UID generieren, wenn kein ox_uid angegeben wurde
     * (z. B. bei lokal erstellten Terminen ohne OX-Sync).
     */
    protected static function booted(): void
    {
        static::creating(function (OxTermin $termin) {
            if (empty($termin->ox_uid)) {
                $termin->ox_uid = Str::uuid()->toString() . '@mitarbeiter.local';
            }
        });
    }

    // --- Relationen ---

    public function kalender(): BelongsTo
    {
        return $this->belongsTo(OxCalendar::class, 'ox_calendar_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }

    public function teilnehmer(): HasMany
    {
        return $this->hasMany(OxTerminTeilnehmer::class, 'ox_termin_id');
    }
}
