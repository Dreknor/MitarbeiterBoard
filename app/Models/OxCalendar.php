<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OxTermin;
use App\Models\OxSyncLog;

/**
 * OxCalendar – Lokale Repräsentation eines OX-CalDAV-Kalenders.
 *
 * @property int         $id
 * @property string      $ox_calendar_id         CalDAV-Pfad / URL
 * @property string      $name
 * @property string      $farbe                  Hex-Farbcode (#RRGGBB)
 * @property string|null $beschreibung
 * @property bool        $sichtbar
 * @property bool        $schreibbar
 * @property string|null $sync_token
 * @property \Carbon\Carbon|null $letzte_synchronisation
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OxTermin> $termine
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OxSyncLog> $syncLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Group> $groups
 * @property-read int|null $termine_count
 */
class OxCalendar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ox_calendars';

    protected $fillable = [
        'ox_calendar_id',
        'name',
        'farbe',
        'beschreibung',
        'sichtbar',
        'schreibbar',
        'sync_token',
        'letzte_synchronisation',
    ];

    protected $casts = [
        'sichtbar'               => 'boolean',
        'schreibbar'             => 'boolean',
        'letzte_synchronisation' => 'datetime',
    ];

    // --- Relationen ---

    public function termine(): HasMany
    {
        return $this->hasMany(OxTermin::class, 'ox_calendar_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(OxSyncLog::class, 'ox_calendar_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'ox_calendar_group')
            ->withPivot('schreibbar')
            ->withTimestamps();
    }

    /**
     * User-spezifische Farbüberschreibungen (TODO 29).
     */
    public function userColors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_calendar_colors')
            ->withPivot('farbe')
            ->withTimestamps();
    }
}
