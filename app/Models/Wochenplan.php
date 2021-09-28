<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Wochenplan extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $table = 'wochenplaene';

    protected $visible = ['gueltig_ab', 'gueltig_bis', 'name', 'bewertung', 'hasDuration'];
    protected $fillable = ['gueltig_ab', 'gueltig_bis', 'group_id', 'name', 'bewertung', 'hasDuration'];

    protected $casts = [
        'gueltig_ab'   => 'datetime',
        'gueltig_bis'   => 'datetime',
        'hasDuration'   => 'boolean'
    ];


    public function group(){
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function rows(){
        return $this->hasMany(WPRows::class);
    }

    public function klassen(){
        return $this->belongsToMany(Klasse::class, wps_klassen::class);
    }

}
