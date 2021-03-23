<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vertretung extends Model
{
    use SoftDeletes;

    protected $table = 'vertretungen';

    protected $fillable = ['date', 'klassen_id', 'users_id', 'stunde', 'comment', 'altFach', 'neuFach'];
    protected $visible = ['date', 'stunde', 'comment', 'altFach', 'neuFach'];

    protected $casts =[
        'date'=> 'datetime:Y-m-d',
    ];

    public function lehrer (){
        return $this->hasOne(User::class, 'id', 'users_id');
    }

    public function klasse (){
        return $this->hasOne(Klasse::class, 'id', 'klassen_id' );
    }
}
