<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = ['task', 'date', 'theme_id', 'completed'];

    protected $dates = [];

    protected $casts = [
        'completed' => 'boolean',
        'date' => 'date'
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

    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('completed', 0);
        });
    }
}
