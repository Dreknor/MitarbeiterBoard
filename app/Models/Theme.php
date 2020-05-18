<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $fillable = ['duration', 'theme', 'information','goal', 'type_id', 'completed'];

    public function ersteller(){
       return $this->belongsTo(User::class, 'creator_id');
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

    public function getPriorityAttribute(){
        if ($this->priorities->count() > 0){
            return $this->priorities->sum('priority')/$this->priorities->count();
        }
        return null;
    }
}
