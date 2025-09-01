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
        return Storage::disk('public')->url($this->image);
    }
}
