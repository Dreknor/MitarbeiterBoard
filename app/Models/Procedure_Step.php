<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedure_Step extends Model
{
    protected $table = 'procedure_steps';

    protected $visible = ['name', 'description', 'durationDays', 'done', 'endDate'];
    protected $fillable = ['name', 'description', 'durationDays', 'done', 'procedure_id', 'parent', 'position_id', 'endDate'];

    protected $casts = [
        'endDate' => 'date'
    ];

    public function position()
    {
        return $this->belongsTo(Positions::class);
    }

    public function parent_rel()
    {
        return $this->belongsTo(self::class, 'parent', 'id');
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function childs()
    {
        return $this->hasMany(self::class, 'parent');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'steps_users', 'steps_id', 'users_id');
    }
}
