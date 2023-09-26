<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $visible = ['module', 'setting_name', 'type', 'value', 'description', 'setting'];
    protected $fillable = ['value'];
}
