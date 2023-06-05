<?php

namespace App\Models\personal;

use App\Models\Group;
use Illuminate\Database\Eloquent\Model;

class RosterCheck extends Model
{
    protected $table = "roster_checks";

    protected $fillable = ['type', 'check_name', 'field_name', 'value', 'weekday', 'department_id', 'operator', 'needs'];
    protected $visible = ['type', 'check_name', 'field_name', 'value', 'weekday', 'department_id', 'operator', 'needs'];

    public function department()
    {
        return $this->belongsTo(Group::class, 'department_id');
    }
}
