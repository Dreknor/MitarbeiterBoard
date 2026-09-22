<?php

namespace App\Models\Wochenplan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WpTaeglicheUebung extends Model
{
    use SoftDeletes;

    protected $table = 'wp_taegliche_uebungen';

    protected $fillable = [
        'wp_plan_id',
        'aufgabe',
        'sort_order',
    ];

    public function plan()
    {
        return $this->belongsTo(WpPlan::class, 'wp_plan_id');
    }
}

