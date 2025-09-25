<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaedDiaryCategory extends Model
{
    protected $fillable = ['name','user_id'];

    public function user(){ return $this->belongsTo(User::class,'user_id'); }
}

