<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'view',
        'default_row',
        'default_col',
        'permission',
    ];

    public function user_cards()
    {
        return $this->belongsToMany(User::class)->withPivot('row', 'col', 'active');
    }
}
