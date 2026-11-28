<?php

namespace App\Models\Wochenplan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fach in einem Plan. Entweder ein Fach aus dem Katalog (`wp_fach_id`, optional umbenannt über
 * `custom_name`) oder ein **freies Feld** ohne Katalog-Fach: dann tragen `custom_name` und `symbol`
 * (Emoji/Zeichen, z. B. „⭐🎄🎁“) Name und Symbol.
 */
class WpPlanFach extends Model
{
    use HasFactory;

    protected $table = 'wp_plan_faecher';
    protected $fillable = ['wp_plan_id', 'wp_fach_id', 'custom_name', 'symbol', 'sort_order'];

    public function plan() { return $this->belongsTo(WpPlan::class, 'wp_plan_id'); }
    public function fach() { return $this->belongsTo(WpFach::class, 'wp_fach_id'); }
    public function aufgaben() {
        return $this->hasMany(WpAufgabe::class, 'wp_plan_fach_id')->orderBy('sort_order');
    }

    public function istFrei(): bool
    {
        return $this->wp_fach_id === null;
    }

    public function getDisplayNameAttribute(): string {
        $name = $this->custom_name ?? ($this->fach ? $this->fach->name : null);

        return $name ?? ($this->symbol ? '' : 'Freies Feld');
    }

    /** Symbol für die Web-Ansicht (eigenes Symbol hat Vorrang vor dem des Katalog-Fachs). */
    public function getSymbolHtmlAttribute(): string
    {
        if ($this->symbol) {
            return '<span class="wp-fach-symbol wp-fach-symbol--emoji">'.e($this->symbol).'</span>';
        }

        return $this->fach?->symbol_html ?? '';
    }

    /** Symbol für DomPDF: Emojis werden einzeln als einfarbige SVG-Grafik eingesetzt. */
    public function getPdfSymbolHtmlAttribute(): string
    {
        $symbol = trim((string) $this->symbol);
        $katalog = mb_strtolower((string) $this->fach?->name);

        // Symbole der Papiervorlage: „5+5“ mit zwei Punktreihen, „ABC“ mit Stift
        if ($symbol === '5+5' || ($symbol === '' && $katalog === 'mathe' && ! $this->fach?->pdf_symbol_html)) {
            return '<div style="font-size:85%;">5+5</div>'.\App\Support\WpUebungsSpalten::punktReihen();
        }
        if ($symbol === 'ABC' || ($symbol === '' && $katalog === 'deutsch' && ! $this->fach?->pdf_symbol_html)) {
            return ($symbol === 'ABC' ? '<div>ABC</div>' : '')
                .'<img src="'.\App\Support\WpUebungsSpalten::stift().'" width="26" height="26" alt="" style="margin-top:1mm;">';
        }
        if ($symbol !== '') {
            return WpFach::zeichenAlsPdfHtml($symbol, null, 22);
        }

        return $this->fach?->pdf_symbol_html ?? '';
    }
}
