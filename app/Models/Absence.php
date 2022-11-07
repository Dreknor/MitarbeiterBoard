<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Absence extends Model
{
    use SoftDeletes;

    protected $fillable = ['users_id', 'creator_id', 'reason', 'start', 'end', 'before', 'showVertretungsplan'];

    protected $casts = [
        'start' =>  'date',
        'end' =>  'date',
        'showVertretungsplan' =>  'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($absence) {
            $absence->creator_id = auth()->id();
        });
    }

    public function user(){
        return $this->belongsTo(User::class, 'users_id')->withDefault([
            'name' => 'System / gelöschter Benutzer']);
    }
}
