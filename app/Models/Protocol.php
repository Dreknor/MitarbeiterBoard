<?php

namespace App\Models;

use App\Mail\newProtocolForTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Protocol extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = ['theme_id', 'creator_id', 'protocol', 'created_at', 'updated_at'];

    public function setProtocolAttribute($protocol){
        $protocol = str_replace('&amp;', ' und ',$protocol);
        $protocol = str_replace('  ', ' ',$protocol);
        $this->attributes['protocol'] = $protocol;
    }

    public function ersteller()
    {
        return $this->belongsTo(User::class, 'creator_id')->withDefault([
            'name' => 'System / gelöschter Benutzer',
        ]);
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class, 'theme_id');
    }

    public function isMemory(){
        return ($this->attributes['protocol'] == "Thema aktiviert" or $this->attributes['protocol']=="Thema in Themenspeicher verschoben")? true : false;
    }
    public function isClosed(){
        return (bool) Str::contains($this->attributes['protocol'], "Thema geschlossen");
    }
    public function isChanged(){
        return (bool) Str::contains($this->attributes['protocol'], "Verschoben zum ");

    }

    //Events
    protected static function booted()
    {
        static::created(function ($protocol) {
            $theme = $protocol->theme;
            foreach ($theme->subscriptionable as $subscription) {
                if ($subscription->user != $protocol->ersteller){
                    Mail::to($subscription->user)->queue(new newProtocolForTask($protocol->ersteller->name, $theme, $theme->group->name, $protocol));
                    //Mail::to($subscription->user)->queue(new newProtocolForTask($protocol->ersteller->name, $theme->theme));
                }
            }
        });
    }
}
