<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Teilnehmer eines Meetings: Person (User), Gruppe/Bereich (Group) oder Rolle.
 */
class MeetingParticipant extends Model
{
    protected $fillable = [
        'meeting_id',
        'participant_type',
        'participant_id',
        'is_organizer',
    ];

    protected $casts = [
        'is_organizer' => 'boolean',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function participant(): MorphTo
    {
        return $this->morphTo();
    }
}
