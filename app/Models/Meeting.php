<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'date',
        'start_time',
        'end_time',
        'title',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'time',
        'end_time' => 'time',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function themes()
    {
        return $this->belongsToMany(Theme::class, 'meeting_themes');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now()->toDateString())
                     ->orderBy('date')
                     ->orderBy('start_time');
    }

    public function scopePast($query)
    {
        return $query->where('date', '<', now()->toDateString())
                     ->orderBy('date', 'desc')
                     ->orderBy('start_time', 'desc');
    }

    public function scopeToday($query)
    {
        return $query->where('date', now()->toDateString())
                     ->orderBy('start_time');
    }


}
