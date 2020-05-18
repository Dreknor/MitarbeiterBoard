<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    protected $fillable = ['type', 'needsProtocol'];

    protected $casts = [
      'needProtocol' =>   "boolean"
    ];
}
