<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaedDiaryEntryPause extends Model
{
    protected $fillable = ['paed_diary_entry_id','schueler_id','date','reason'];
    protected $casts = ['date'=>'date'];

    public function entry(){ return $this->belongsTo(PaedDiaryEntry::class,'paed_diary_entry_id'); }
    public function schueler(){ return $this->belongsTo(Schueler::class,'schueler_id'); }
}

