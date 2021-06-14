<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Items extends Model
{

    protected $table = 'inv_items';
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'description', 'date', 'price', 'oldInvNumber', 'location_id', 'category_id', 'lieferant_id'];
    protected $visible = ['uuid', 'name', 'description', 'date', 'price', 'oldInvNumber', 'lieferant_id'];

    public function category(){
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function location(){
        return $this->belongsTo(Location::class, 'category_id');
    }
    public function lieferant(){
        return $this->belongsTo(Lieferant::class, 'lieferant_id');
    }
}
