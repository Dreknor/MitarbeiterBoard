<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Liste extends Model
{
    protected $table = 'listen';

    protected $fillable = ['listenname', 'type', 'comment', 'user_id', 'visible_for_all', 'active', 'ende', 'duration', 'multiple'];

    protected $casts = [
        'ende' => 'datetime',
        'visible_for_all'   => 'boolean',
        'active'            => 'boolean',
        'multiple'            => 'boolean',
    ];

    public function ersteller()
    {
        return $this->belongsTo(User::class, 'user_id' );
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_listen', 'liste_id');
    }

    public function eintragungen()
    {
        return $this->hasMany(ListenTermin::class, 'listen_id');
    }

    public function scopeActive($query)
    {
        $query->where('active', 1)
            ->orWhere('listen.user_id', auth()->id());
    }
}
