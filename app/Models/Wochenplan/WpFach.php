<?php
namespace App\Models\Wochenplan;
use Illuminate\Database\Eloquent\Model;
class WpFach extends Model
{
    protected $table = 'wp_faecher';
    protected $fillable = ['name', 'sort_order', 'is_default'];
    protected $casts = ['is_default' => 'boolean', 'sort_order' => 'integer'];
    public function scopeDefault($query) { return $query->where('is_default', true); }
    public function scopeOrdered($query) { return $query->orderBy('sort_order')->orderBy('name'); }
    public function planFaecher() { return $this->hasMany(WpPlanFach::class, 'wp_fach_id'); }
}
