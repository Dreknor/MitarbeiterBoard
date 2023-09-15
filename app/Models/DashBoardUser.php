<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use function Clue\StreamFilter\fun;

class DashBoardUser extends Model
{
    use HasFactory;
    protected $table = 'dashboard_card_user';

    protected $fillable = [
        'dashboard_card_id',
        'user_id',
        'row',
        'col',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected function title(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->dashboardCard->title;
            },
        );
    }

    protected function view(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->dashboardCard->view;
            },
        );
    }
    protected function permission(): Attribute
    {
        return Attribute::make(
            get: function () {
                return \Cache::remember('card_'.$this->dashboardCard->id, 60 * 60 * 25, function (){
                    return $this->dashboardCard->permission;
                });
            },
        );
    }

    public function dashboardCard()
    {
        return $this->belongsTo(DashboardCard::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
    public function scopeNotActive($query)
    {
        return $query->where('active', 0);
    }

    public function scopeOrder($query)
    {
        return $query->orderBy('row')->orderBy('col');
    }


}
