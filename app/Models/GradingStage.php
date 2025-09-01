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

    // Liefert öffentlich zugängliche URL zum Stufen-Bild (wenn vorhanden)
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        if (str_starts_with($this->image, 'http')) return $this->image;

        // Prefer files placed directly in public/ (no storage symlink required).
        // Check several common public locations and return an asset() URL if found.
        $candidates = [
            $this->image, // if image already includes a path relative to public
            'img/' . $this->image,
            'images/' . $this->image,
            'img/stages/' . $this->image,
            'images/stages/' . $this->image,
        ];
        foreach ($candidates as $rel) {
            $abs = public_path($rel);
            if (file_exists($abs)) {
                // build web path relative to public
                $web = str_replace('\\','/', ltrim(str_replace(public_path(), '', $abs), '/'));
                return asset($web);
            }
        }

        // Fallback: check storage public disk (if still used)
        try {
            if (Storage::disk('public')->exists($this->image)) {
                return Storage::disk('public')->url($this->image);
            }
        } catch (\Throwable $_) {
            // ignore and fall through to null
        }

        return null;
    }
}
