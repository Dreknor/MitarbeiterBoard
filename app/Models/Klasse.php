<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Klasse extends Model
{
    use SoftDeletes;

    protected $table = 'klassen';

    protected $visible = ['name'];
    protected $fillable = ['name'];

    public function wochenplaene(){
        return $this->hasManyThrough(Wochenplan::class, wps_klassen::class);
    }
}
