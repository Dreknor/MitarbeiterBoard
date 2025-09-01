<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchuelerGradingHistory extends Model
{
    protected $table = 'schueler_grading_histories';

    public $timestamps = false; // created_at wird manuell gesetzt

    protected $fillable = [
        'schueler_id',
        'grading_system_id',
        'grading_stage_id',
        'previous_grading_stage_id',
        'changed_by',
        'paed_diary_entry_id',
        'created_at'
    ];

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function stage()
    {
        return $this->belongsTo(GradingStage::class, 'grading_stage_id');
    }

    public function previous_stage()
    {
        return $this->belongsTo(GradingStage::class, 'previous_grading_stage_id');
    }

    public function changed_by_user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function paed_diary_entry()
    {
        return $this->belongsTo(PaedDiaryEntry::class, 'paed_diary_entry_id');
    }
}

