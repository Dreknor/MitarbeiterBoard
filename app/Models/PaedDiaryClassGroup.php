<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaedDiaryClassGroup extends Model
{
    protected $fillable = ['user_id','name'];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function klassen(){
        return $this->belongsToMany(Klasse::class,'paed_diary_class_group_klasse','group_id','klasse_id');
    }
}

