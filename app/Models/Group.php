<?php

namespace App\Models;

use App\Models\Liste;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name', 'creator_id', 'enddate', 'homegroup', 'InvationDays', 'protected', 'hasWochenplan'];
    protected $visible = ['name', 'creator_id', 'enddate', 'homegroup', 'InvationDays', 'protected', 'hasWochenplan'];

    protected $dates = ['enddate'];
    protected $casts = [
        'protected' => 'boolean',
        'hasWochenplan' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function themes()
    {
        return $this->hasMany(Theme::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function home()
    {
        return $this->belongsTo(self::class, 'homegroup');
    }

    /**
     * Get all of the tasks.
     */
    public function tasks()
    {
        return $this->morphMany(\App\Models\Task::class, 'taskable');
    }


    /**
     * Get all of the tasks.
     */
    public function wochenplaene()
    {
        return $this->hasMany(Wochenplan::class, 'group_id');
    }

    /**
     * Get all of the Listen.
     */
    public function listen()
    {
        return $this->belongsToMany(Liste::class, 'group_listen', 'group_id', 'liste_id');
    }

    /**
     * Get all of the subscriptions.
     */
    public function subscriptionable()
    {
        return $this->morphMany(Subscription::class, 'subscriptionable');
    }
}
