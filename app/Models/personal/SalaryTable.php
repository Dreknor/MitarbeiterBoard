<?php

namespace App\Models\personal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class SalaryTable extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pers_salary_tables';

    protected $fillable = [
        'name',
        'base_reference',
        'valid_from',
        'valid_until',
        'data',
        'notes',
    ];

    protected $casts = [
        'valid_from'  => 'date',
        'valid_until' => 'date',
        'data'        => 'array',
    ];

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    /**
     * Prüft ob diese Gehaltstabelle aktuell gültig ist.
     */
    public function isCurrentlyValid(): bool
    {
        return $this->valid_from->isPast()
            && ($this->valid_until === null || $this->valid_until->isFuture());
    }
}

