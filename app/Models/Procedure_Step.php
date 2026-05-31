<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedure_Step extends Model
{
    use HasFactory;

    protected $table = 'procedure_steps';

    protected static function newFactory(): \Database\Factories\ProcedureStepFactory
    {
        return \Database\Factories\ProcedureStepFactory::new();
    }

    protected $visible = ['name', 'description', 'durationDays', 'done', 'endDate', 'completed_at', 'completed_by'];
    protected $fillable = ['name', 'description', 'durationDays', 'done', 'procedure_id', 'parent', 'sort_order', 'position_id', 'endDate', 'completed_at', 'completed_by', 'template_step_id'];

    protected $casts = [
        'endDate'      => 'date',
        'completed_at' => 'datetime',
    ];

    public function position()
    {
        return $this->belongsTo(Positions::class);
    }

    public function parent_rel()
    {
        return $this->belongsTo(self::class, 'parent', 'id');
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function childs()
    {
        return $this->hasMany(self::class, 'parent');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'steps_users', 'steps_id', 'users_id');
    }

    public function comments()
    {
        return $this->hasMany(ProcedureStepComment::class, 'step_id')->latest();
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function templateStep()
    {
        return $this->belongsTo(ProcedureTemplateStep::class, 'template_step_id');
    }

    public function histories()
    {
        return $this->hasMany(ProcedureStepHistory::class, 'step_id')->orderByDesc('created_at');
    }
}
