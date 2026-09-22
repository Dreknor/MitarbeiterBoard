<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Zeitraster extends Model
{
    protected $table    = 'zeitraster';
    protected $fillable = ['name', 'beschreibung', 'ist_standard'];
    protected $casts    = ['ist_standard' => 'boolean'];

    /**
     * Relation: hat viele LessonTimes (geordnet nach Stunde)
     */
    public function lessonTimes()
    {
        return $this->hasMany(LessonTime::class, 'zeitraster_id')->orderBy('period');
    }

    /**
     * Relation: hat viele Klassen
     */
    public function klassen()
    {
        return $this->hasMany(Klasse::class, 'zeitraster_id');
    }

    /**
     * Gibt das Standard-Zeitraster zurück (gecacht, TTL 3600s).
     */
    public static function getStandard(): ?self
    {
        return Cache::remember('zeitraster_standard', 3600,
            fn () => static::where('ist_standard', true)->first()
        );
    }

    /**
     * Setzt dieses Zeitraster als Standard, entfernt das Flag von allen anderen
     * und invalidiert den Cache.
     */
    public function markAlsStandard(): void
    {
        DB::transaction(function () {
            static::where('ist_standard', true)->update(['ist_standard' => false]);
            $this->update(['ist_standard' => true]);
        });

        Cache::forget('zeitraster_standard');
        $this->refresh();
    }
}

