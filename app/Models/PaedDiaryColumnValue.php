<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaedDiaryColumnValue extends Model
{
    protected $fillable=['paed_diary_column_id','schueler_id','datum','value'];
    protected $casts=['datum'=>'date'];
    public function column(){return $this->belongsTo(PaedDiaryColumn::class,'paed_diary_column_id');}
    public function schueler(){return $this->belongsTo(Schueler::class);}
}

