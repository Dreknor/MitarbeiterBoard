<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcedureTemplateStep extends Model
{
    use HasFactory;

    protected $table = 'procedure_template_steps';

    protected $fillable = [
        'template_id', 'parent_id', 'position_id', 'name', 'description',
        'durationDays', 'sort_order', 'legacy_step_id',
    ];

    public function template()
    {
        return $this->belongsTo(ProcedureTemplate::class, 'template_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function position()
    {
        return $this->belongsTo(Positions::class, 'position_id');
    }
}

