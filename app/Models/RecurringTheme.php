<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;

class RecurringTheme extends Model implements HasMedia
{
    use \Spatie\MediaLibrary\InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = ['theme', 'information', 'goal', 'type_id', 'creator_id', 'group_id', 'type_id', 'created_at', 'updated_at', 'month'];

    public function ersteller()
    {
        return $this->belongsTo(User::class, 'creator_id')->withDefault([
            'name' => 'System / gelöschter Benutzer',
        ]);
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }



    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }


}
