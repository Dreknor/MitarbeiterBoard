<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualificationType extends Model
{
    use HasFactory;

    protected $table = 'pers_qualification_types';

    protected $fillable = [
        'name',
        'category',
        'validity_months',
        'reminder_days',
        'applies_to',
        'description',
        'is_active',
    ];

    protected $casts = [
        'applies_to' => 'array',
        'is_active'  => 'boolean',
    ];

    public function employeeQualifications(): HasMany
    {
        return $this->hasMany(EmployeeQualification::class, 'qualification_type_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'qualification_type_id');
    }
}

