<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTheme extends Model
{
    use SoftDeletes;

    protected $fillable = ['theme', 'information', 'goal', 'type_id', 'creator_id', 'group_id', 'type_id', 'created_at', 'updated_at', 'month'];

    public function ersteller()
    {
        return $this->belongsTo(User::class, 'creator_id')->withDefault([
            'name' => 'System / gelöschter Benutzer',
        ]);
    }

    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }


}
