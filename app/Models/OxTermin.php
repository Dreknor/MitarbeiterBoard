<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OxTerminTeilnehmer;

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
