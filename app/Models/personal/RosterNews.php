<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RosterNews extends Model
{
    use HasFactory;

    protected $fillable = ['news'];
    protected $visible = ['news'];

    public function roster()
    {
        return $this->belongsTo(Roster::class);
    }

}
