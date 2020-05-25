<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{

    use SoftDeletes;

    protected $fillable = ['task', 'date', 'theme_id'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'date'];

    public function theme () {
        return $this->belongsTo(Theme::class);
    }

    public function taskable()
    {
        return $this->morphTo();
    }
}
