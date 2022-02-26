<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = ['task', 'date', 'theme_id', 'completed'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'date'];

    protected $casts = [
        'completed' => 'boolean',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function taskable()
    {
        return $this->morphTo();
    }

    public function taskUsers(){
        return $this->hasMany(GroupTaskUser::class, 'taskable_id');
    }
}
