<?php
namespace App\Models\Wochenplan;
use Illuminate\Database\Eloquent\Model;
class WpFach extends Model
{
    protected $table = 'wp_faecher';
    protected $fillable = ['name', 'sort_order', 'is_default', 'symbol_typ', 'symbol_wert', 'symbol_farbe'];
    protected $casts = ['is_default' => 'boolean', 'sort_order' => 'integer'];
    public function scopeDefault($query) { return $query->where('is_default', true); }
    public function scopeOrdered($query) { return $query->orderBy('sort_order')->orderBy('name'); }
    public function planFaecher() { return $this->hasMany(WpPlanFach::class, 'wp_fach_id'); }

    /**
     * Gibt fertig gerenderten HTML-String für das Symbol zurück.
     */
    public function getSymbolHtmlAttribute(): string
    {
        if (!$this->symbol_typ || $this->symbol_typ === 'keine') {
            return '';
        }
        $colorStyle = $this->symbol_farbe ? 'color:' . e($this->symbol_farbe) . ';' : '';
        return match ($this->symbol_typ) {
            'emoji' => '<span class="wp-fach-symbol wp-fach-symbol--emoji" style="' . $colorStyle . '">'
                       . e($this->symbol_wert) . '</span>',
            'svg'   => '<span class="wp-fach-symbol wp-fach-symbol--svg">'
                       . $this->symbol_wert . '</span>',
            'bild'  => '<img class="wp-fach-symbol wp-fach-symbol--bild" '
                       . 'src="' . e($this->symbol_wert) . '" alt="" aria-hidden="true" '
                       . 'style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;">',
            default => '',
        };
    }
}
