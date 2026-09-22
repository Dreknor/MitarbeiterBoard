<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedureStepHistory extends Model
{
    public $timestamps = false;

    protected $table = 'procedure_step_history';

    protected $fillable = ['step_id', 'type', 'performed_by', 'meta'];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function step()
    {
        return $this->belongsTo(Procedure_Step::class, 'step_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ── Convenience-Factory-Methoden ──────────────────────────────────────

    public static function logUserAdded(int $stepId, User $target, ?int $performedBy): void
    {
        static::create([
            'step_id'      => $stepId,
            'type'         => 'user_added',
            'performed_by' => $performedBy,
            'meta'         => ['user_id' => $target->id, 'user_name' => $target->name],
        ]);
    }

    public static function logUserRemoved(int $stepId, User $target, ?int $performedBy): void
    {
        static::create([
            'step_id'      => $stepId,
            'type'         => 'user_removed',
            'performed_by' => $performedBy,
            'meta'         => ['user_id' => $target->id, 'user_name' => $target->name],
        ]);
    }

    public static function logPositionChanged(int $stepId, ?Positions $old, ?Positions $new, ?int $performedBy): void
    {
        static::create([
            'step_id'      => $stepId,
            'type'         => 'position_changed',
            'performed_by' => $performedBy,
            'meta'         => [
                'old_position_id'   => $old?->id,
                'old_position_name' => $old?->name,
                'new_position_id'   => $new?->id,
                'new_position_name' => $new?->name,
            ],
        ]);
    }

    public static function logReopened(int $stepId, ?int $performedBy): void
    {
        static::create([
            'step_id'      => $stepId,
            'type'         => 'reopened',
            'performed_by' => $performedBy,
            'meta'         => null,
        ]);
    }
}

