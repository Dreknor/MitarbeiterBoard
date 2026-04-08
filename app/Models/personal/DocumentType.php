<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $table = 'pers_document_types';

    protected $fillable = [
        'name',
        'category',
        'requires_expiry',
        'default_reminder_days',
        'nextcloud_subfolder',
        'is_active',
    ];

    protected $casts = [
        'requires_expiry' => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(PersonalDocument::class, 'document_type_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'document_type_id');
    }
}

