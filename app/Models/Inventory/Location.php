<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use SoftDeletes;

    protected $table = 'inv_locations';

    protected $visible = ['uuid', 'kennzeichnung', 'name', 'description'];
    protected $fillable = ['uuid', 'kennzeichnung', 'name', 'description', 'locationtype_id', 'verantwortlicher_id'];




    public function type(){
        return $this->belongsTo(LocationType::class, 'locationtype_id', 'id');
    }
    public function items(){
        return $this->hasMany(Items::class, 'location_id');
    }

    public function verantwortlicher(){
        return $this->belongsTo(User::class, 'verantwortlicher_id', 'id');
    }
}
