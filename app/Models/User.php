<?php

namespace App\Models;


use App\Models\personal\EmployeData;
use App\Models\personal\EmployeHolidayClaim;
use App\Models\personal\Employment;
use App\Models\personal\Holiday;
use App\Models\personal\RosterEvents;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\personal\WorkingTime;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 *
 */
class User extends Authenticatable implements HasMedia
{
    use Notifiable;
    use HasRoles;
    use HasPushSubscriptions;
    use SoftDeletes;
    use HasRelationships;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'changePassword','kuerzel', 'absence_abo_daily', 'absence_abo_now', 'username','remind_assign_themes',
    ];
    protected $visible = [
        'name', 'email', 'password', 'changePassword','kuerzel', 'absence_abo_daily', 'absence_abo_now', 'username','remind_assign_themes',
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
        'absence_abo_now' => 'boolean',
        'remind_assign_themes' => 'boolean',
    ];


    public function getGeburtstagAttribute(){
        return $this->employe_data?->geburtstag;
    }

    public function survey_user_answers()
    {
        return $this->hasMany(SurveyUserAnswer::class, 'user_id');
    }

    public function employe_data(){
        return $this->hasOne(EmployeData::class);
    }
    public function posts(){
        return $this->hasManyDeep(Post::class, ['group_user', Group::class, 'group_post'])->distinct() ;
    }

    public function themes()
    {
        return $this->hasMany(Theme::class, 'creator_id');
    }

    public function assigned_themes()
    {
        return $this->hasMany(Theme::class, 'assigned_to');
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
        return $this->belongsToMany(Group::class)->orderBy('name');
    }

    public function dashboardCards()
    {
        return $this->hasMany(DashBoardUser::class,  'user_id');
    }

    /**
     * Get all of the tasks.
     */
    public function tasks()
    {
        return $this->morphMany(\App\Models\Task::class, 'taskable');
    }

    /**
     * get Group Tasks
     */
    public function group_tasks()
    {
        return $this->hasMany(GroupTaskUser::class, 'users_id');
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

        if ($this->employe_data != null and $this->employe_data->familienname != null){
            $familiename = $this->employe_data->familienname;
        } else {
            $familiename= Str::afterLast($this->name, ' ');
        }

        return Str::limit($this->name, 1, '.').' '.$familiename;
    }

    /**
     * @return string|null
     */
    public function getVornameAttribute(){

        if ($this->employe_data != null and $this->employe_data->vorname != null){
            return $this->employe_data->vorname;
        }

        $vorname= Str::before($this->name, ' ');
        return $vorname;
    }

    public function getFamiliennameAttribute(){

        if ($this->employe_data != null and $this->employe_data->familienname != null){
            return $this->employe_data->familienname;
        }

        $name= Str::afterLast($this->name, ' ');
        return $name;
    }

    public function listen()
    {
        return $this->hasManyDeep(Liste::class, ['group_user', Group::class, 'group_listen']);
    }

    public function listen_eintragungen()
    {
        return $this->hasMany(ListenTermin::class, 'reserviert_fuer');
    }

    //Dienstpläne

    public function holiday_claim(){
        return $this->hasMany(EmployeHolidayClaim::class, 'employe_id');
    }

    #TODO neue anlegen
    public function getHolidayClaim(Carbon $year = null){
        if ($year == null) {$year = Carbon::now();}

        $claim = $this->holiday_claim()->whereDate('date_start', '<=', $year)->orderByDesc('date_start')->first();

        return ($claim == null)? 26 : $claim->holiday_claim;
    }

    public function working_times()
    {
        return $this->hasMany(WorkingTime::class, 'employe_id');
    }

    public function roster_events()
    {
        return $this->hasMany(RosterEvents::class);
    }

    public function employments()
    {
        return $this->hasMany(Employment::class, 'employe_id');
    }

    public function employments_date(DateTime $date = null, DateTime $end = null)
    {
        if (is_null($date)) {
            $date = Carbon::now();
        }
        if (is_null($end)) {
            $end = $date;
        }

        $employments = Cache::remember('user_employments_'.$this->id, 1, function (){
            return $this->employments;
        });

        return $employments->filter(function ($item) use ($date, $end){
                return $item->start->startOfDay()->lessThanOrEqualTo($date) and (is_null($item->end) or $item->end->addDay()->startOfDay()->greaterThan($end->endOfDay()));
        });

    }

    public function holidays(){
        return $this->hasMany(Holiday::class, 'employe_id');
    }

    public function hasHoliday(Carbon $start_date, Carbon $end_date = null){

        if (is_null($end_date)){
            $end_date = $start_date;
        }

            $holidays = $this->holidays;

            $found = $holidays->filter(function ($item) use ($start_date, $end_date){
                if ($item->start_date->between($start_date, $end_date)
                    or $item->end_date->between($start_date, $end_date))
                {
                    return $item;
                }
            })->first();

            if ($found != null){
                return true;
            } else {
                return false;
            }
    }

    public function timesheets(){
        return $this->hasMany(Timesheet::class, 'employe_id');
    }
    public function timesheet_days(){
        return $this->hasManyThrough(TimesheetDays::class, Timesheet::class, 'employe_id');
    }
    public function getTimesheetLatestAttribute(){
        return $this->timesheets()->orderByDesc('year')->orderByDesc('month')->first();
    }

    public function photo(){

        return Cache::remember('user_photo_'.$this->id, 60*60*24, function (){
            if ($this->getMedia('profile')->count() == 0){
                return asset('img/avatar.png') ;
            } else {
                return url('image/').'/'.$this->getMedia('profile')->first()->id;
            }
        });

    }
}
