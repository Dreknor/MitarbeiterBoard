<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PaedDiaryAppointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_date',
        'start_time',
        'end_time',
        'is_recurring',
        'recurring_type',
        'recurring_interval',
        'recurring_end_date',
        'is_paused',
        'pause_entries',
    ];

    protected $casts = [
        'start_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'recurring_end_date' => 'date',
        'is_recurring' => 'boolean',
        'is_paused' => 'boolean',
        'recurring_interval' => 'integer',
        'pause_entries' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function klassen()
    {
        return $this->belongsToMany(Klasse::class, 'paed_diary_appointment_klassen');
    }

    public function groups()
    {
        return $this->belongsToMany(PaedDiaryClassGroup::class, 'paed_diary_appointment_groups', 'paed_diary_appointment_id', 'paed_diary_class_group_id');
    }

    public function schueler()
    {
        return $this->belongsToMany(Schueler::class, 'paed_diary_appointment_schueler');
    }

    public function exceptions()
    {
        return $this->hasMany(PaedDiaryAppointmentException::class, 'appointment_id');
    }

    /**
     * Berechnet alle Termine für einen Zeitraum unter Berücksichtigung von Wiederholungen
     * und gesetzten Ausnahmen (übersprungene einzelne Vorkommen).
     */
    public function getOccurrencesInRange(Carbon $startDate, Carbon $endDate)
    {
        $occurrences = [];

        if (!$this->is_recurring) {
            // Einmaliger Termin
            if ($this->start_date->between($startDate, $endDate)) {
                $occurrences[] = [
                    'id' => $this->id,
                    'title' => $this->title,
                    'description' => $this->description,
                    'date' => $this->start_date->toDateString(),
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'is_recurring' => false,
                    'is_paused' => $this->is_paused,
                    'user_id' => $this->user_id,
                ];
            }
            return $occurrences;
        }

        // Wiederkehrender Termin
        if ($this->is_paused) {
            return [];
        }

        // Ausnahmedaten laden (bereits eager-loaded oder lazy-loaded)
        $exceptionDates = $this->exceptions
            ->pluck('exception_date')
            ->map(fn ($d) => $d->toDateString())
            ->flip()
            ->toArray();

        $current = $this->start_date->copy();
        $maxDate = $this->recurring_end_date ?
            min($endDate, $this->recurring_end_date) : $endDate;

        while ($current->lte($maxDate)) {
            // Ausnahme überspringen
            if (!isset($exceptionDates[$current->toDateString()])) {
                if ($current->between($startDate, $endDate)) {
                    $occurrences[] = [
                        'id' => $this->id,
                        'title' => $this->title,
                        'description' => $this->description,
                        'date' => $current->toDateString(),
                        'start_time' => $this->start_time,
                        'end_time' => $this->end_time,
                        'is_recurring' => true,
                        'is_paused' => false,
                        'user_id' => $this->user_id,
                    ];
                }
            }

            // Nächsten Termin berechnen
            switch ($this->recurring_type) {
                case 'daily':
                    $current->addDays($this->recurring_interval);
                    break;
                case 'weekly':
                    $current->addWeeks($this->recurring_interval);
                    break;
                case 'monthly':
                    $current->addMonths($this->recurring_interval);
                    break;
            }
        }

        return $occurrences;
    }

    /**
     * Scope für aktive (nicht pausierte) Termine
     */
    public function scopeActive($query)
    {
        return $query->where('is_paused', false);
    }

    /**
     * Scope für Termine eines bestimmten Benutzers
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Prüft, ob dieser Termin am angegebenen Datum ein Vorkommen hat.
     * Berücksichtigt Ausnahmen (exceptions), Wiederholungsintervalle und Enddatum.
     */
    public function isOccurrenceOn(Carbon $date): bool
    {
        if ($this->is_paused) return false;

        $dateStr = $date->toDateString();

        // Ausnahmen prüfen (eager- oder lazy-loaded)
        if ($this->relationLoaded('exceptions')) {
            foreach ($this->exceptions as $exc) {
                if ($exc->exception_date->toDateString() === $dateStr) return false;
            }
        }

        if (!$this->is_recurring) {
            return $this->start_date->toDateString() === $dateStr;
        }

        if ($date->lt($this->start_date)) return false;
        if ($this->recurring_end_date && $date->gt($this->recurring_end_date)) return false;

        $diff = (int) $this->start_date->copy()->startOfDay()->diffInDays($date->copy()->startOfDay());

        return match ($this->recurring_type) {
            'daily'   => $diff % $this->recurring_interval === 0,
            'weekly'  => ($diff % (7 * $this->recurring_interval)) === 0,
            'monthly' => (function () use ($date): bool {
                $monthsDiff = ($date->year - $this->start_date->year) * 12
                            + ($date->month - $this->start_date->month);
                return $monthsDiff % $this->recurring_interval === 0
                    && $date->day === $this->start_date->day;
            })(),
            default   => false,
        };
    }
}
