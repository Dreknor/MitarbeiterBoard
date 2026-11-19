<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Wert einer Zusatzspalte für einen Schüler an einem Tag.
 *
 * bis_wochenende: Der Wert gilt ab seinem Tag bis Freitag derselben Woche weiter, bis zur nächsten
 * ausdrücklichen Änderung (siehe PaedDiarySpaltenwertService). Wird beim Speichern aus der Spalte
 * übernommen.
 */
class PaedDiaryColumnValue extends Model
{
    protected $fillable=['paed_diary_column_id','schueler_id','datum','value','bis_wochenende'];
    protected $casts=['datum'=>'date', 'bis_wochenende' => 'boolean'];
    public function column(){return $this->belongsTo(PaedDiaryColumn::class,'paed_diary_column_id');}
    public function schueler(){return $this->belongsTo(Schueler::class);}
}
