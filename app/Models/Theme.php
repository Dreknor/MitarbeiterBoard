<?php

namespace App\Models;

use App\Mail\NewThemeMail;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Theme extends Model implements HasMedia
{
    use InteractsWithMedia;



    protected $fillable = ['duration', 'theme', 'information','goal', 'type_id', 'completed', 'creator_id', 'type_id','created_at', 'updated_at', 'date'];

    protected $dates = ['created_at', 'updated_at', 'date'];

    public function ersteller(){
       return $this->belongsTo(User::class, 'creator_id')->withDefault([
           'name' => 'System / gelöschter Benutzer',
       ]);;
    }

    public function type(){
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function priorities (){
        return $this->hasMany(Priority::class, 'theme_id');
    }

    public function protocols(){
        return $this->hasMany(Protocol::class, 'theme_id');
    }

    public function group(){
      return $this->belongsTo(Group::class);
    }

    public function tasks(){
        return $this->hasMany(Task::class);
    }

    public function getPriorityAttribute(){
        if ($this->priorities->count() > 0){
            return $this->priorities->sum('priority')/$this->priorities->count();
        }
        return null;
    }

    public function share(){
        return $this->hasOne(Share::class);
    }

    /**
     * Get all of the subscriptions.
     */
    public function subscriptionable()
    {
        return $this->morphMany(Subscription::class, 'subscriptionable');
    }



    //Events
    protected static function booted()
    {
        static::created(function ($theme) {
            $group = $theme->group;

            //dd($group->subscriptionable);
            foreach ($group->subscriptionable as $subscription){
                Mail::to($subscription->user)->queue(new NewThemeMail($theme->theme, $group->name ));
            }

        });
    }
}
