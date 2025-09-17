<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PaedDiaryColumnValue; // Import ergänzt

class PaedDiaryColumn extends Model
{
    protected $fillable=['klasse_id','name','slug','type','sort_order','active','deactivated_from','category'];
    protected $casts = [
        'deactivated_from' => 'date'
    ];
    public function klasse(){return $this->belongsTo(Klasse::class);}
    public function values(){return $this->hasMany(PaedDiaryColumnValue::class,'paed_diary_column_id');}
}
