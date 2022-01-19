<?php

namespace App\Models;

use App\Mail\newProtocolForTask;
use App\Mail\NewThemeMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;

class Protocol extends Model
{
    use SoftDeletes;

    protected $fillable = ['theme_id', 'creator_id', 'protocol', 'created_at', 'updated_at'];

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

    //Events
    protected static function booted()
    {
        static::created(function ($protocol) {
            $theme = $protocol->theme;

            //dd($group->subscriptionable);
            foreach ($theme->subscriptionable as $subscription) {
                Mail::to($subscription->user)->queue(new newProtocolForTask($protocol->ersteller->name, $theme->theme));
            }
        });
    }
}
