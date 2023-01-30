<?php

namespace App\Models;

use App\Mail\newProtocolForTask;
use App\Mail\NewThemeMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Theme extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['memory','duration', 'theme', 'information', 'goal', 'type_id', 'completed', 'creator_id', 'group_id', 'created_at', 'updated_at', 'date', 'assigned_to'];

    protected $dates = ['created_at', 'updated_at', 'date'];

    protected $casts = [
      'memory'  => 'boolean'
    ];

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id')->withDefault([
           'name' => 'System / gelöschter Benutzer',
       ]);
    }

    public function zugewiesen_an(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function priorities(): HasMany
    {
        return $this->hasMany(Priority::class, 'theme_id');
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class, 'theme_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function getPriorityAttribute(): float|int|null
    {
        if ($this->priorities->count() > 0) {

            if ($this->priorities->sum('priority') == 100){
                return 99 / $this->priorities->count();

            }

            return $this->priorities->sum('priority') / $this->priorities->count();
        }


        return null;
    }

    public function share(): HasOne
    {
        return $this->hasOne(Share::class);
    }

    /**
     * Get all the subscriptions.
     */
    public function subscriptionable(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriptionable');
    }

    //Events
    protected static function booted()
    {
        static::created(function ($theme) {
            $group = $theme->group;

            //dd($group->subscriptionable);
            foreach ($group->subscriptionable as $subscription) {
                Mail::to($subscription->user)->queue(new NewThemeMail($theme->theme, $theme->id, $group->name));
            }
        });
    }
}
