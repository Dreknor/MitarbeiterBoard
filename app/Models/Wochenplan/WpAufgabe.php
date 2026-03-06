<?php
namespace App\Models\Wochenplan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class WpAufgabe extends Model
{
    use SoftDeletes;
    protected $table = 'wp_aufgaben';
    protected $fillable = ['wp_plan_fach_id', 'aufgabe', 'dauer', 'sort_order', 'synced_from_id'];
    public function planFach() { return $this->belongsTo(WpPlanFach::class, 'wp_plan_fach_id'); }
    public function syncedFrom() { return $this->belongsTo(self::class, 'synced_from_id'); }
    public function isSynced(): bool { return $this->synced_from_id !== null; }
}
