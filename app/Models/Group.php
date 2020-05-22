<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [ 'name', 'creator_id', 'enddate', 'homegroup'];

    protected $dates = ['enddate'];

    public function users (){
        return $this->belongsToMany(User::class);
    }

    public function themes(){
        return $this->hasMany(Theme::class);
    }

    public function creator(){
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function home(){
        return $this->belongsTo(Group::class, 'homegroup');
    }



}
