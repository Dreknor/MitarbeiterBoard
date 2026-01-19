<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaedDiaryTask extends Model
{
    use SoftDeletes;
    protected $fillable=['klasse_id','schueler_id','title','description','due_date','status','highlighted','created_by','closed_at'];
    protected $casts=['due_date'=>'date','closed_at'=>'datetime'];
    public function klasse(){return $this->belongsTo(Klasse::class);}
    public function schueler(){return $this->belongsTo(Schueler::class);}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
    public function scopeOpen($q){return $q->where('status','open');}
}

