<?php
namespace App\Models\Wochenplan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class WpFormatvorlage extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'wp_formatvorlagen';
    protected $fillable = [
        'name', 'beschreibung', 'schriftgroesse', 'schriftart',
        'layout_config', 'blade_template', 'is_default', 'created_by',
    ];
    protected $casts = ['layout_config' => 'array', 'is_default' => 'boolean'];
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function plaene() { return $this->hasMany(WpPlan::class, 'formatvorlage_id'); }
    public function getSchriftgroesseCssAttribute(): string {
        return match ($this->schriftgroesse) {
            'gross'      => 'text-lg',
            'sehr_gross' => 'text-xl',
            default      => 'text-sm',
        };
    }
    public function config(string $key, mixed $default = null): mixed {
        return data_get($this->layout_config, $key, $default);
    }
}
