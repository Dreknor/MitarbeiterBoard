<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    protected $table = "inv_categories";

    protected $fillable = ['parent_id', 'name'];
    protected $visible = ['name'];

    public function parent(){
        return $this->belongsTo(InventoryCategory::class, 'parent_id', 'id');
    }
}
