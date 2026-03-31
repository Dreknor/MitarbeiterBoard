<?php

namespace App\Models\personal;

use App\Enums\ParticipantStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $table = 'pers_training_participants';

    protected $fillable = [
        'training_id',
        'employe_id',
        'status',
        'certificate_document_id',
        'feedback',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'status'      => ParticipantStatus::class,
    ];

    // ---- Relationships ----

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function certificateDocument(): BelongsTo
    {
        return $this->belongsTo(PersonalDocument::class, 'certificate_document_id');
    }
}

