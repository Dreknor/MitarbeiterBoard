<?php
namespace App\Models\Wochenplan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class WpFormatvorlage extends Model
{
    use HasFactory, SoftDeletes;
    /** Erlaubte PDF-Vorlagen (Blade-Views unter wochenplan.pdf.*). */
    public const TEMPLATES = [
        'wochenplan.pdf.standard'    => 'Standard',
        'wochenplan.pdf.gross'       => 'Große Schrift (Legasthenie)',
        'wochenplan.pdf.individuell' => 'Individuell',
    ];

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
    /** Gibt immer eine erlaubte PDF-Vorlage zurück (Fallback: Standard). */
    public function sicheresTemplate(): string {
        return array_key_exists((string) $this->blade_template, self::TEMPLATES)
            ? $this->blade_template
            : 'wochenplan.pdf.standard';
    }
    public function config(string $key, mixed $default = null): mixed {
        return data_get($this->layout_config, $key, $default);
    }
}
