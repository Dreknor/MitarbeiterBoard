<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationType extends Model
{

    protected $table = 'inv_locationtypes';
    protected $fillable = ['name', 'description'];

}
