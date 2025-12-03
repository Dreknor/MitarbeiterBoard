<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Stufen dieses Bereichs
     */
    public function stages()
    {
        return $this->hasMany(DiagnosticStage::class)->orderBy('sort_order');
    }

    /**
     * Sessions dieses Bereichs
     */
    public function sessions()
    {
        return $this->hasMany(DiagnosticSession::class);
    }

    /**
     * Scope für aktive Bereiche
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope für sortierte Bereiche
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}

