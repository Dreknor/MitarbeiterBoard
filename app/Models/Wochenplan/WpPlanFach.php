<?php
namespace App\Models\Wochenplan;
use Illuminate\Database\Eloquent\Model;
class WpPlanFach extends Model
{
    protected $table = 'wp_plan_faecher';
    protected $fillable = ['wp_plan_id', 'wp_fach_id', 'custom_name', 'sort_order'];
    public function plan() { return $this->belongsTo(WpPlan::class, 'wp_plan_id'); }
    public function fach() { return $this->belongsTo(WpFach::class, 'wp_fach_id'); }
    public function aufgaben() {
        return $this->hasMany(WpAufgabe::class, 'wp_plan_fach_id')->orderBy('sort_order');
    }
    public function getDisplayNameAttribute(): string {
        return $this->custom_name ?? ($this->fach ? $this->fach->name : 'Unbekanntes Fach');
    }
}
