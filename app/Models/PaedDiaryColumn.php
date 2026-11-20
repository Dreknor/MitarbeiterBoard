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

    protected $fillable=['klasse_id','spalten_uid','name','slug','type','sort_order','active','deactivated_from','category','bis_wochenende'];
    protected $casts = [
        'deactivated_from' => 'date',
        'bis_wochenende' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Kopien derselben Spalte (gleicher Name, Typ und Gruppe) teilen eine spalten_uid.
        // Wer keine mitgibt, wird mit einer vorhandenen gleichen Spalte verknüpft oder bekommt eine neue.
        static::creating(function (PaedDiaryColumn $column) {
            if (empty($column->spalten_uid)) {
                $column->spalten_uid = self::vorhandeneUid($column->name, $column->type, $column->category)
                    ?? (string) \Illuminate\Support\Str::uuid();
            }
        });

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

    /** Name bzw. Gruppe für Vergleiche: ohne Leerzeichen am Rand, ohne Groß-/Kleinschreibung. */
    public static function normalisiert(?string $wert): string
    {
        return mb_strtolower(trim((string) $wert));
    }

    /** Abfrage auf Spalten mit gleichem Name und gleicher Gruppe (normalisiert verglichen). */
    public function scopeGleichBenannt($query, ?string $name, ?string $category)
    {
        return $query->whereRaw('LOWER(TRIM(name)) = ?', [self::normalisiert($name)])
            ->whereRaw("LOWER(TRIM(COALESCE(category, ''))) = ?", [self::normalisiert($category)]);
    }

    /** spalten_uid einer vorhandenen Spalte mit gleichem Name, Typ und Gruppe (sonst null). */
    public static function vorhandeneUid(?string $name, ?string $type, ?string $category): ?string
    {
        return self::query()->gleichBenannt($name, $category)
            ->where('type', $type)
            ->whereNotNull('spalten_uid')
            ->orderBy('id')
            ->value('spalten_uid');
    }

    /** Alle Kopien dieser Spalte (alle Klassen, auch deaktivierte) inkl. dieser. */
    public function kopien()
    {
        return self::where('spalten_uid', $this->spalten_uid);
    }

    /** Gilt ein heute gesetzter Wert dieser Spalte bis Freitag weiter? */
    public function wirktBisWochenende(): bool
    {
        return (bool) $this->bis_wochenende && in_array($this->type, self::TYPEN_BIS_WOCHENENDE, true);
    }

    public function klasse(){return $this->belongsTo(Klasse::class);}
    public function values(){return $this->hasMany(PaedDiaryColumnValue::class,'paed_diary_column_id');}
}
