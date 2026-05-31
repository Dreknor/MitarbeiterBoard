<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kommentar an einem Prozess-Schritt (§8.3).
 */
class ProcedureStepComment extends Model
{
    use SoftDeletes;

    protected $table = 'procedure_step_comments';

    protected $fillable = ['step_id', 'user_id', 'body', 'notified_at'];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function step()
    {
        return $this->belongsTo(Procedure_Step::class, 'step_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

