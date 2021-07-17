<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Items extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'inv_items';
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'description', 'date', 'price', 'oldInvNumber', 'location_id', 'category_id', 'lieferant_id', 'status', 'number'];
    protected $visible = ['uuid', 'name', 'description', 'date', 'price', 'oldInvNumber', 'lieferant_id', 'status', 'number'];

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
        return  QrCode::size(50)->generate(url('item/'.$this->uuid));
    }
}
