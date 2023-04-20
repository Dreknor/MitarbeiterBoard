<?php

namespace App\Models;

use App\Models\Liste;
use App\Models\personal\Employment;
use App\Models\personal\Roster;
use App\Models\personal\RosterCheck;
use App\Models\Subscription;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class Group extends Model
{
    use HasRelationships;

    protected $fillable = ['name', 'creator_id', 'enddate', 'homegroup', 'InvationDays', 'protected', 'hasWochenplan', 'needsRoster', 'hasAllocations', 'viewType', 'information_template'];
    protected $visible = ['name', 'creator_id', 'enddate', 'homegroup', 'InvationDays', 'protected', 'hasWochenplan', 'needsRoster', 'hasAllocations', 'viewType', 'information_template'];

    protected $casts = [
        'protected' => 'boolean',
        'hasWochenplan' => 'boolean',
        'needsRoster' => 'boolean',
        'hasAllocations' => 'boolean',
        'enddate'  => 'date'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function themes()
    {
        return $this->hasMany(Theme::class);
    }

    public function recurringThemes()
    {
        return $this->hasMany(RecurringTheme::class);
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
     * Get all of the posts.
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'group_id');
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

    //rosters

    public function rosters()
    {
        return $this->hasMany(Roster::class, 'department_id');
    }


    public function employments()
    {
        return $this->hasMany(Employment::class, 'department_id');
    }

    public function roster_checks()
    {
        return $this->hasMany(RosterCheck::class, 'department_id');
    }

    public function employes()
    {
        return $this->hasManyDeep(User::class, ['employments'], ['department_id','id'], [null,'department_id' ,'employe_id']);
    }

    public function activeEmployes(DateTime $date, DateTime $end = null)
    {
        if ($end == null) {
            $end = $date;
        }


        $employes = $this->employes()
            ->where([
            ['employments.start', '<=', $end],
            ['employments.end', '=', null],
                ['department_id', '=', $this->id]
        ])->orWhere(
            [
                ['employments.start', '<=', $end],
                ['employments.end', '>=', $date],
                ['department_id', '=', $this->id]
            ])->get();

        return $employes->unique('id');
    }
}
