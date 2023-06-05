<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeHolidayClaim extends Model
{
    use HasFactory;
    protected $fillable = ['holiday_claim', 'employe_id', 'changedBy', 'date_start'];
    protected $visible = ['holiday_claim', 'employe_id', 'changedBy','date_start'];


    protected $casts = [
        'date_start' => 'date'
    ];
    public function employe(){
        return $this->belongsTo(User::class, 'employe_id');
    }
    public function created_by(){
        return $this->belongsTo(User::class, 'changedBy');
    }
}
