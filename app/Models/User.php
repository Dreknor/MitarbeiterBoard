<?php

namespace App\Models;

use App\Models\Subscription;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;
   use HasPushSubscriptions;
   use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'changePassword'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function themes(){
        return $this->hasMany(Theme::class, 'creator_id');
    }

    public function groups(){

        return Cache::remember("groups_".$this->id, 60, function() {
            $groups = $this->groups_rel;
            $groups = $groups->concat(Group::where('protected', '0')->get());
            $groups = $groups->unique('name');
            return $groups;
        });


    }


    /**
     * This method can be used when we want to utilise a cache
     */
    public function groups_rel()
    {
        return $this->belongsToMany(Group::class);
    }

    /**
     * Get all of the tasks.
     */
    public function tasks()
    {
        return $this->morphMany('App\Models\Task', 'taskable');
    }
    /**
     * Get all of the Subscription.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'users_id');
    }



}
