<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\PaedDiaryCategory; // import category model

class PaedDiaryEntry extends Model
{
    use HasFactory;
    /** @var array<int,string> */
    protected $fillable = [ 'klasse_id','user_id','datum','content','completed_at','category_id', 'dossier_only' ]; // completed_at und category_id ergänzt

    /** @var array<string,string> */
    protected $casts = [
        'datum' => 'date',
        'completed_at'=>'datetime',
        'dossier_only'=>'boolean'
        ];

    // Beziehungen
    public function klasse(){ return $this->belongsTo(Klasse::class); }
    public function user(){ return $this->belongsTo(User::class); }
    public function schueler(){ return $this->belongsToMany(Schueler::class,'paed_diary_entry_schueler'); }
    public function pauses(){ return $this->hasMany(PaedDiaryEntryPause::class,'paed_diary_entry_id'); }
    public function category(){ return $this->belongsTo(PaedDiaryCategory::class,'category_id'); }

    /**
     * Scope (API v1): Einträge einer Klasse.
     */
    public function scopeForClass($query, int $classId)
    {
        return $query->where($this->qualifyColumn('klasse_id'), $classId);
    }

    /**
     * Scope (API v1): Einträge, die einem Schüler zugeordnet sind (klassenunabhängig,
     * damit Einträge aus früheren Schuljahren erhalten bleiben).
     */
    public function scopeForSchueler($query, int $schuelerId)
    {
        return $query->whereHas('schueler', fn ($q) => $q->where('schueler.id', $schuelerId));
    }

    /**
     * Scope (API v1): Einträge aus den Klassen, denen die Lehrkraft zugeordnet ist.
     */
    public function scopeForTeacher($query, int $teacherId)
    {
        return $query->whereIn($this->qualifyColumn('klasse_id'), function ($sub) use ($teacherId) {
            $sub->select('klasse_id')->from('klasse_user')->where('user_id', $teacherId);
        });
    }

    /**
     * Scope (API v1): Vertrauliche Einträge (dossier_only) nur für berechtigte Benutzer.
     * Autoren sehen ihre eigenen vertraulichen Einträge immer.
     *
     * @param bool $includeConfidential Bei false werden vertrauliche Einträge auch für Berechtigte ausgeblendet.
     */
    public function scopeConfidentialFilter($query, User $user, bool $includeConfidential = true)
    {
        if ($includeConfidential && $user->canViewConfidentialDiaryEntries()) {
            return $query;
        }

        $dossierColumn = $this->qualifyColumn('dossier_only');
        $userColumn = $this->qualifyColumn('user_id');

        return $query->where(function ($q) use ($user, $includeConfidential, $dossierColumn, $userColumn) {
            $q->where($dossierColumn, false)->orWhereNull($dossierColumn);
            if ($includeConfidential) {
                $q->orWhere($userColumn, $user->id);
            }
        });
    }

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
