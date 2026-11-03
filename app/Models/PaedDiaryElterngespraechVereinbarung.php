<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vereinbarung aus einem Elterngespräch. Kann als Aufgabe oder Ziel in die Schülerakte
 * übernommen werden (paed_diary_task_id / paed_diary_goal_id).
 */
class PaedDiaryElterngespraechVereinbarung extends Model
{
    public const VERANTWORTLICH = [
        'eltern' => 'Eltern',
        'schule' => 'Schule',
        'schueler' => 'Schüler:in',
        'gemeinsam' => 'Gemeinsam',
    ];

    protected $table = 'paed_diary_elterngespraech_vereinbarungen';

    protected $fillable = [
        'elterngespraech_id', 'text', 'verantwortlich', 'bis', 'sort_order',
        'paed_diary_task_id', 'paed_diary_goal_id',
    ];

    protected $casts = [
        'text' => 'encrypted',
        'bis' => 'date',
        'sort_order' => 'integer',
    ];

    public function gespraech()
    {
        return $this->belongsTo(PaedDiaryElterngespraech::class, 'elterngespraech_id');
    }

    public function task()
    {
        return $this->belongsTo(PaedDiaryTask::class, 'paed_diary_task_id');
    }

    public function goal()
    {
        return $this->belongsTo(PaedDiaryGoal::class, 'paed_diary_goal_id');
    }
}
