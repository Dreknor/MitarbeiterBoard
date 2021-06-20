<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class Lieferant extends Model
{
    protected $table = 'inv_lieferanten';

    protected $visible = ['kuerzel', 'name'];
    protected $fillable = ['kuerzel', 'name'];


}
