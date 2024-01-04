<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vertretung extends Model
{
    use SoftDeletes;

    protected $table = 'vertretungen';

    protected $fillable = ['date', 'klassen_id', 'users_id', 'stunde', 'comment', 'altFach', 'neuFach', 'Doppelstunde', 'type'];
    protected $visible = ['id','date', 'stunde', 'Doppelstunde', 'comment', 'altFach', 'neuFach', 'type'];

    protected $casts =[
        'date'=> 'date',
        'Doppelstunde'=>'boolean'
    ];

    public function lehrer (){
        return $this->hasOne(User::class, 'id', 'users_id');
    }

    public function klasse (){
        return $this->hasOne(Klasse::class, 'id', 'klassen_id' )->withDefault([
            'name' => 'gelöschte Klasse',
        ]);
    }

    public function getStundeAttribute(){
        if ($this->attributes['Doppelstunde'] == true){
            $increment = $this->attributes['stunde'];
            $increment++;
            return $this->attributes['stunde'].'. / '.$increment.'.';
        }
        return $this->attributes['stunde'];
    }


}
