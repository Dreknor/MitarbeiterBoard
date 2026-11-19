<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PaedDiaryColumnValue; // Import ergänzt

class PaedDiaryColumn extends Model
{
    use HasFactory;

    /** Spaltentypen, bei denen „bis Wochenende“ wirkt (Ja/Nein und Ampel). */
    public const TYPEN_BIS_WOCHENENDE = ['boolean', 'ampel'];

    protected $fillable=['klasse_id','name','slug','type','sort_order','active','deactivated_from','category','bis_wochenende'];
    protected $casts = [
        'deactivated_from' => 'date',
        'bis_wochenende' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Eine Spalte übernimmt immer die Einstellung „bis Wochenende“ der Spaltengruppe, in der sie
        // gerade ist – beim Anlegen und beim Verschieben in eine andere (oder keine) Gruppe.
        // Ausdrücklich gesetzte Werte (z. B. beim Umschalten der ganzen Gruppe) bleiben unberührt.
        static::saving(function (PaedDiaryColumn $column) {
            if ($column->isDirty('bis_wochenende')) {
                return;
            }
            if ($column->exists && !$column->isDirty('category')) {
                return;
            }
            $column->bis_wochenende = self::gruppeBisWochenende($column->category, $column->id);
        });
    }

    /**
     * Hat die Spaltengruppe (Kategorie) die Einstellung „bis Wochenende“?
     * Maßgeblich sind die übrigen Spalten mit diesem Gruppennamen (klassenübergreifend).
     */
    public static function gruppeBisWochenende(?string $category, ?int $ohneId = null): bool
    {
        $category = trim((string) $category);
        if ($category === '') {
            return false;
        }

        return self::where('category', $category)
            ->when($ohneId, fn ($q) => $q->where('id', '!=', $ohneId))
            ->where('bis_wochenende', true)
            ->exists();
    }

    /** Gilt ein heute gesetzter Wert dieser Spalte bis Freitag weiter? */
    public function wirktBisWochenende(): bool
    {
        return (bool) $this->bis_wochenende && in_array($this->type, self::TYPEN_BIS_WOCHENENDE, true);
    }

    public function klasse(){return $this->belongsTo(Klasse::class);}
    public function values(){return $this->hasMany(PaedDiaryColumnValue::class,'paed_diary_column_id');}
}
