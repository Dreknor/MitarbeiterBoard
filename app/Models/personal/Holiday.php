<?php

namespace App\Models\personal;

use App\Models\Absence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'employe_id',
        'start_date',
        'end_date',
        'approved',
        'approved_by',
        'approved_at',
        'rejected',
        'days',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved' => 'boolean',
        'rejected' => 'boolean',
    ];

    public function employe()
    {
        return $this->belongsTo(User::class);
    }

   public function approved_by()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
