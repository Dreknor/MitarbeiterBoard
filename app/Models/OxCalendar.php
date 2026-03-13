<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OxTermin;
use App\Models\OxSyncLog;

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
}
