<?php

namespace App\Models\Wochenplan;

use App\Models\PaedDiaryTask;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WpAufgabe extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wp_aufgaben';

    protected $fillable = [
        'wp_plan_fach_id', 'aufgabe', 'dauer', 'niveau', 'link', 'sort_order',
        'synced_from_id', 'angepasst', 'paed_diary_task_id',
    ];

    protected $casts = [
        'niveau' => 'integer',
        'sort_order' => 'integer',
        'angepasst' => 'boolean',
        'synced_from_id' => 'integer',
        'paed_diary_task_id' => 'integer',
        'wp_plan_fach_id' => 'integer',
    ];

    public function planFach()
    {
        return $this->belongsTo(WpPlanFach::class, 'wp_plan_fach_id');
    }

    public function syncedFrom()
    {
        return $this->belongsTo(self::class, 'synced_from_id');
    }

    public function paedDiaryTask()
    {
        return $this->belongsTo(PaedDiaryTask::class, 'paed_diary_task_id');
    }

    public function statusEintraege()
    {
        return $this->hasMany(WpAufgabeStatus::class, 'wp_aufgabe_id');
    }

    public function isSynced(): bool
    {
        return $this->synced_from_id !== null && ! $this->angepasst;
    }
}
