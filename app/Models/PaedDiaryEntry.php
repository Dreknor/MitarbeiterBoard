<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PaedDiaryEntry extends Model
{
    /** @var array<int,string> */
    protected $fillable = [ 'klasse_id','user_id','datum','content','completed_at' ]; // completed_at ergänzt

    /** @var array<string,string> */
    protected $casts = [ 'datum' => 'date','completed_at'=>'datetime' ]; // Cast ergänzt

    // Beziehungen
    public function klasse(){ return $this->belongsTo(Klasse::class); }
    public function user(){ return $this->belongsTo(User::class); }
    public function schueler(){ return $this->belongsToMany(Schueler::class,'paed_diary_entry_schueler'); }

    /**
     * Mutator: verschlüsselt den Inhalt vor dem Speichern (Application-Level Encryption).
     * Verhindert Doppelverschlüsselung, indem immer vom Klartext (Request) ausgegangen wird.
     *
     * @param string|null $value
     * @return void
     */
    public function setContentAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['content'] = $value; // leer belassen
            return;
        }
        // Immer Klartext -> verschlüsseln
        try {
            $this->attributes['content'] = encrypt($value);
        } catch (\Throwable $e) {
            Log::warning('PaedDiaryEntry encryption failed: '.$e->getMessage());
            // Fallback: Rohwert speichern (besser als Datenverlust) – wird später bei Migration erneut versucht
            $this->attributes['content'] = $value;
        }
    }

    /**
     * Accessor: entschlüsselt den gespeicherten Inhalt transparent.
     * Falls der Wert (noch) unverschlüsselt ist (Alt-Daten), wird er unverändert zurückgegeben.
     *
     * @param string|null $value
     * @return string|null
     */
    public function getContentAttribute($value): ?string
    {
        if ($value === null || $value === '') return $value;
        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            // Vermutlich Altbestand im Klartext oder beschädigte Daten
            return $value;
        }
    }
}
