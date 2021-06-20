<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;

class Items extends Model implements HasMedia
{
    use \Spatie\MediaLibrary\InteractsWithMedia;

    protected $table = 'inv_items';
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'description', 'date', 'price', 'oldInvNumber', 'location_id', 'category_id', 'lieferant_id', 'status'];
    protected $visible = ['uuid', 'name', 'description', 'date', 'price', 'oldInvNumber', 'lieferant_id', 'status'];

    protected $dates = ['date'];

    public function category(){
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function location(){
        return $this->belongsTo(Location::class, 'location_id');
    }
    public function lieferant(){
        return $this->belongsTo(Lieferant::class, 'lieferant_id');
    }

    public function QR(){
        return  \SimpleSoftwareIO\QrCode\Facades\QrCode::size(50)->generate(url('item/'.$this->uuid));
    }
}
