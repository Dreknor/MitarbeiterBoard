<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WPRows extends Model
{
    use HasFactory;
    protected $table = 'wprows';

    protected $visible = ['name'];
    protected $fillable = ['name', 'wochenplan_id'];

    public function wochenplan(){
        return $this->belongsTo(Wochenplan::class);
    }
}
