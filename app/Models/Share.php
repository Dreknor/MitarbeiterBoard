<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Share extends Model
{

    use SoftDeletes;


    protected $fillable = ['uuid', 'theme_id','creators_id', 'readonly', 'activ_until'];
    protected $visible = ['uuid', 'theme_id', 'readonly', 'activ_until'];

    protected $casts = [
        'readonly'  => 'boolean',
    ];



    public function theme () {
        return $this->belongsTo(Theme::class);
    }
    public function creator () {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'System / gelöschter Benutzer',
        ]);;
    }

    public function getActivUntilAttribute($date){

        if ($date){
            return Carbon::createFromFormat('Y-m-d', $date);
        }
        return null;
    }

}
