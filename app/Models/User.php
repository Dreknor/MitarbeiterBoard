<?php

namespace App\Models;


use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;
    use HasPushSubscriptions;
    use SoftDeletes;
    use HasRelationships;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'changePassword','kuerzel', 'absence_abo_daily', 'absence_abo_now', 'username'
    ];protected $visible = [
        'name', 'email', 'password', 'changePassword','kuerzel', 'absence_abo_daily', 'absence_abo_now', 'username'
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
        'absence_abo_daily' => 'boolean',
        'absence_abo_now' => 'boolean'
    ];

    public function themes()
    {
        return $this->hasMany(Theme::class, 'creator_id');
    }

    public function groups()
    {
        return Cache::remember('groups_'.$this->id, 60, function () {
            $groups = $this->groups_rel;

            if ($this->can('see unprotected groups')){
                $groups = $groups->concat(Group::where('protected', '0')->get());
            }
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
        return $this->morphMany(\App\Models\Task::class, 'taskable');
    }

    /**
     * Get all of the Subscription.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'users_id');
    }

    public function steps()
    {
        return $this->belongsToMany(Procedure_Step::class, 'steps_users', 'users_id', 'steps_id');
    }

    public function vertretungen(){
        return $this->hasMany(Vertretung::class, 'users_id', 'id');
    }

    public function positions(){
        return $this->belongsToMany(Positions::class, 'position_user', 'user_id', 'position_id');
    }


    public function getShortnameAttribute(){
        $familiename= Str::afterLast($this->name, ' ');
        return Str::limit($this->name, 1, '.').' '.$familiename;
    }

    public function listen()
    {
        return $this->hasManyDeep(Liste::class, ['group_user', Group::class, 'group_listen']);
    }

    public function listen_eintragungen()
    {
        return $this->hasMany(ListenTermin::class, 'reserviert_fuer');
    }


}
