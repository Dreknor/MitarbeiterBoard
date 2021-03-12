<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Positions extends Model
{
    protected $fillable = ['name'];
    protected $visible = ['name'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'position_user', 'position_id', 'user_id');
    }
}
