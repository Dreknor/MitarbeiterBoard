<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = "inv_categories";

    protected $fillable = ['parent_id', 'name'];
    protected $visible = ['name'];

    public function parent(){
        return $this->belongsTo(Category::class, 'parent_id', 'id');
    }

    public function items(){
        return $this->hasMany(Items::class);
    }
}
