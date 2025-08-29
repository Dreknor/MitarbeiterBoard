<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaedDiaryEntry extends Model
{
    protected $fillable = [
        'klasse_id','user_id','datum','content'
    ];
    protected $casts = [
        'datum' => 'date'
    ];

    public function klasse(){ return $this->belongsTo(Klasse::class); }
    public function user(){ return $this->belongsTo(User::class); }
    public function schueler(){ return $this->belongsToMany(Schueler::class,'paed_diary_entry_schueler'); }
}

