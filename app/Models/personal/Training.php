<?php

namespace App\Models\personal;

use App\Enums\TrainingStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Training extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pers_trainings';

    protected $fillable = [
        'title',
        'description',
        'provider',
        'start_date',
        'end_date',
        'location',
        'cost',
        'max_participants',
        'qualification_type_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'cost'       => 'decimal:2',
        'status'     => TrainingStatus::class,
    ];

    // ---- Relationships ----

    public function qualificationType(): BelongsTo
    {
        return $this->belongsTo(QualificationType::class, 'qualification_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class, 'training_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class, 'training_id')
            ->whereNotIn('status', ['abgesagt']);
    }

    // ---- Helper ----

    public function isFull(): bool
    {
        if (is_null($this->max_participants)) return false;
        return $this->activeParticipants()->count() >= $this->max_participants;
    }

    public function freePlaces(): ?int
    {
        if (is_null($this->max_participants)) return null;
        return max(0, $this->max_participants - $this->activeParticipants()->count());
    }
}

