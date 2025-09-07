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
    ];

    protected $casts = [
        'start_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'recurring_end_date' => 'date',
        'is_recurring' => 'boolean',
        'is_paused' => 'boolean',
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

    /**
     * Berechnet alle Termine für einen Zeitraum unter Berücksichtigung von Wiederholungen
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
                ];
            }
            return $occurrences;
        }

        // Wiederkehrender Termin
        if ($this->is_paused) {
            return [];
        }

        $current = $this->start_date->copy();
        $maxDate = $this->recurring_end_date ?
            min($endDate, $this->recurring_end_date) : $endDate;

        while ($current->lte($maxDate)) {
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
                ];
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
}
