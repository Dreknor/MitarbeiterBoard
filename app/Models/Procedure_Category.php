<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedure_Category extends Model
{

    protected $table = 'procedure_categories';
    protected $fillable = ['name'];
    protected $visible = ['name'];

    public function procedures(){
        return $this->hasMany(Procedure::class);
    }
}
