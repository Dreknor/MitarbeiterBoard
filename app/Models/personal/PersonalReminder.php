<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wiedervorlage der Personalverwaltung (Probezeit, Vertragsende, Aufbewahrungsfrist).
 */
class PersonalReminder extends Model
{
    public const TYPE_PROBATION    = 'probation';
    public const TYPE_CONTRACT_END = 'contract_end';
    public const TYPE_RETENTION    = 'retention';

    protected $table = 'pers_reminders';

    protected $fillable = ['employe_id', 'employment_id', 'type', 'due_date', 'lead_days', 'note', 'notified_at', 'done_at'];

    protected $casts = [
        'due_date'    => 'date',
        'notified_at' => 'datetime',
        'done_at'     => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('done_at');
    }

    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_PROBATION    => 'Probezeit endet',
            self::TYPE_CONTRACT_END => 'Befristeter Vertrag endet',
            self::TYPE_RETENTION    => 'Aufbewahrungsfrist endet',
            default                 => 'Wiedervorlage',
        };
    }
}
