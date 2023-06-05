<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;

class Address extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['employe_id', 'plz', 'ort', 'nr', 'strasse'];

    public function employe(){
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employe')
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty();
    }
}
