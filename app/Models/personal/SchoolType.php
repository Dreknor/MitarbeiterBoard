<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolType extends Model
{
    use HasFactory;

    protected $table = 'pers_school_types';

    protected $fillable = [
        'name',
        'default_deputat',
        'stundentafel',
        'is_active',
    ];

    protected $casts = [
        'stundentafel'    => 'array',
        'default_deputat' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function teacherDetails(): HasMany
    {
        return $this->hasMany(TeacherDetail::class);
    }
}

