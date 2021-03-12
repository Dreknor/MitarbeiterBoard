<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procedure extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'author_id', 'category_id', 'started_at', 'ended_at'];
    protected $visible = ['name', 'description', 'author_id', 'category_id', 'started_at', 'ended_at'];

    protected $dates = ['started_at', 'ended_at'];

    public function category()
    {
        return $this->belongsTo(Procedure_Category::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class);
    }

    public function steps()
    {
        return $this->hasMany(Procedure_Step::class);
    }
}
