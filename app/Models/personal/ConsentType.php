<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsentType extends Model
{
    use HasFactory;

    protected $table = 'pers_consent_types';

    protected $fillable = [
        'name',
        'description',
        'legal_basis',
        'key',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }
}

