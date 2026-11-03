<?php

namespace App\Models\personal;

use App\Models\Absence;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Urlaubsantrag.
 *
 * Status (abgeleitet): beantragt → genehmigt | abgelehnt;
 * genehmigt → Stornierung beantragt → storniert (Soft-Delete) oder wieder genehmigt.
 * Alle Statuswechsel laufen über App\Services\Personal\Zeit\HolidayService.
 */
class Holiday extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_BEANTRAGT = 'beantragt';
    public const STATUS_GENEHMIGT = 'genehmigt';
    public const STATUS_ABGELEHNT = 'abgelehnt';
    public const STATUS_STORNO = 'storno_beantragt';

    protected $fillable = [
        'employe_id',
        'start_date',
        'end_date',
        'half_day',
        'comment',
        'approved',
        'approved_by',
        'approved_at',
        'rejected',
        'rejection_reason',
        'cancellation_requested_at',
        'cancellation_reason',
        'cancelled_by',
        'days',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'half_day' => 'boolean',
        'approved' => 'boolean',
        'rejected' => 'boolean',
        'approved_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'days' => 'float',
    ];

    public function employe()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function approved_by()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approved_by_user()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function absences()
    {
        return $this->hasMany(Absence::class, 'holiday_id');
    }

    // ---- Status ----

    public function getStatusAttribute(): string
    {
        if ($this->rejected) {
            return self::STATUS_ABGELEHNT;
        }
        if ($this->approved && $this->cancellation_requested_at !== null) {
            return self::STATUS_STORNO;
        }
        if ($this->approved) {
            return self::STATUS_GENEHMIGT;
        }

        return self::STATUS_BEANTRAGT;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_GENEHMIGT => 'Genehmigt',
            self::STATUS_ABGELEHNT => 'Abgelehnt',
            self::STATUS_STORNO => 'Stornierung beantragt',
            default => 'Beantragt',
        };
    }

    public function getIsPendingAttribute(): bool
    {
        return !$this->approved && !$this->rejected;
    }

    public function getDaysLabelAttribute(): string
    {
        $days = (float) ($this->days ?? 0);

        return rtrim(rtrim(number_format($days, 1, ',', ''), '0'), ',').' '.($days == 1.0 ? 'Tag' : 'Tage');
    }

    // ---- Scopes ----

    public function scopeGenehmigt(Builder $query): Builder
    {
        return $query->where('approved', true)->where('rejected', false);
    }

    public function scopeOffen(Builder $query): Builder
    {
        return $query->where('approved', false)->where('rejected', false);
    }

    public function scopeNichtAbgelehnt(Builder $query): Builder
    {
        return $query->where('rejected', false);
    }

    public function scopeUeberschneidet(Builder $query, $start, $end): Builder
    {
        return $query->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start);
    }
}
