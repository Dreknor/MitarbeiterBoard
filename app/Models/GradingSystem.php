<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\GradingStage;

class GradingSystem extends Model
{
    protected $table = 'grading_systems';

    protected $fillable = ['name','slug','description','active'];

    public function stages()
    {
        return $this->hasMany(GradingStage::class, 'grading_system_id')->orderBy('sort_order');
    }
}
