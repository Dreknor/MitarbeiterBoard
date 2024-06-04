<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Presence extends Model
{
    use SoftDeletes;

    public $fillable = [
        'group_id',
        'date',
        'user_id',
        'presence',
        'excused',
        'online',
        'guest_name',
        'created_by',
        'created_at',
    ];

    public $casts = [
        'date' => 'date',
        'presence' => 'boolean',
    ];
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeGroup($query, $group)
    {
        return $query->where('group_id', $group);
    }
}
