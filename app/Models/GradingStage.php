<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GradingStage extends Model
{
    protected $table = 'grading_stages';

    protected $fillable = ['grading_system_id','name','slug','symbol','image','sort_order','is_default'];

    // Cast is_default to boolean to ensure true/false values
    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function system()
    {
        return $this->belongsTo(GradingSystem::class, 'grading_system_id');
    }

    /** Je Request ermittelte URLs alter Bildpfade (vermeidet wiederholte file_exists-Aufrufe) */
    private static array $legacyUrls = [];

    /**
     * Öffentliche URL des Stufenbilds (wenn vorhanden).
     *
     * Neue Uploads liegen unter public/img/stages und werden als "img/stages/…" gespeichert –
     * dafür wird die URL ohne Dateisystemzugriff gebildet. Ältere Einträge (nur Dateiname oder
     * Pfad auf der Storage-Disk "public") werden wie bisher gesucht, das Ergebnis aber gemerkt.
     */
    public function getImageUrlAttribute(): ?string
    {
        $image = $this->image ? ltrim(str_replace('\\', '/', $this->image), '/') : null;
        if (!$image) return null;
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) return $image;

        if (str_starts_with($image, 'img/') || str_starts_with($image, 'images/')) {
            return asset($image);
        }

        if (!array_key_exists($image, self::$legacyUrls)) {
            self::$legacyUrls[$image] = self::legacyUrl($image);
        }

        return self::$legacyUrls[$image];
    }

    private static function legacyUrl(string $image): ?string
    {
        foreach ([$image, 'img/stages/' . $image, 'images/stages/' . $image, 'img/' . $image, 'images/' . $image] as $rel) {
            if (file_exists(public_path($rel))) {
                return asset($rel);
            }
        }

        // Fallback: Storage-Disk "public" (falls noch genutzt)
        try {
            if (Storage::disk('public')->exists($image)) {
                return Storage::disk('public')->url($image);
            }
        } catch (\Throwable $_) {
            // ignorieren
        }

        return null;
    }
}
