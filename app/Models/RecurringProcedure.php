<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringProcedure extends Model
{

    protected $fillable = [
        'name',
        'procedure_id',
        'month',
        'faelligkeit_typ',
        'wochen',
        'ferien',
    ];

    protected $casts = [
        'month' => 'integer',
        'wochen' => 'integer',

    ];

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

}
