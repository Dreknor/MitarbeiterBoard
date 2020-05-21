<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Theme extends Model implements HasMedia
{
    use InteractsWithMedia;



    protected $fillable = ['duration', 'theme', 'information','goal', 'type_id', 'completed', 'creator_id', 'type_id','created_at', 'updated_at', 'date'];

    protected $dates = ['created_at', 'updated_at', 'date'];

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

    public function group(){
      return $this->belongsTo(Group::class);
    }

    public function getPriorityAttribute(){
        if ($this->priorities->count() > 0){
            return $this->priorities->sum('priority')/$this->priorities->count();
        }
        return null;
    }

}
