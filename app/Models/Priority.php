<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{

    protected $fillable = ['creator_id', 'theme_id', 'priority'];

    public function theme(){
        return $this->belongsTo(Theme::class, 'theme_id');
    }

    public function creator(){
        return $this->belongsTo(User::class, 'creator_id');
    }
}
