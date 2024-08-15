<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;

    protected $fillable = [
      'author_id', 'header', 'text', 'released', 'created_at'
    ];

    public function author(){
        return $this->belongsTo(User::class, 'author_id', 'id')->withDefault([
            'name' => 'System / gelöschter Benutzer',
        ]);
    }

    public function groups(){
        return $this->belongsToMany(Group::class);
    }
}
