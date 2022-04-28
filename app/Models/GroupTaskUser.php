<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTaskUser extends Model
{

    protected $fillable =['taskable_id', 'users_id'];

    public function task(){
        return $this->belongsTo(Task::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }

}
