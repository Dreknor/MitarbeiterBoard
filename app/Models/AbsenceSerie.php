<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Regel einer wiederkehrenden Abwesenheit. Die Termine werden beim Anlegen einmalig als einzelne
 * Abwesenheiten erzeugt (AbsenceSerieService) – die Regel dient danach nur noch der Beschreibung.
 */
class AbsenceSerie extends Model
{
    use SoftDeletes;

    public const RHYTHMEN = [
        'woechentlich' => 'wöchentlich',
        'monatlich' => 'monatlich',
    ];

    /** ISO-Wochentage, die gewählt werden können (Wochenenden haben keine Termine) */
    public const WOCHENTAGE = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag'];

    public const POSITIONEN = [1 => '1.', 2 => '2.', 3 => '3.', 4 => '4.', -1 => 'letzten'];

    protected $table = 'absence_series';

    protected $fillable = [
        'users_id', 'creator_id', 'reason', 'rhythmus', 'wochentage', 'intervall',
        'monat_position', 'monat_wochentag', 'start', 'end', 'showVertretungsplan',
    ];

    protected $casts = [
        'wochentage' => 'array',
        'intervall' => 'integer',
        'monat_position' => 'integer',
        'monat_wochentag' => 'integer',
        'start' => 'date',
        'end' => 'date',
        'showVertretungsplan' => 'boolean',
    ];

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class, 'serie_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id')->withDefault(['name' => 'System / gelöschter Benutzer']);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id')->withDefault(['name' => 'System']);
    }

    /**
     * z. B. „jeden Dienstag und Donnerstag“, „alle 2 Wochen montags“, „jeden letzten Freitag im Monat“
     */
    public function rhythmusText(): string
    {
        if ($this->rhythmus === 'monatlich') {
            return 'jeden '.(self::POSITIONEN[$this->monat_position] ?? '?').' '
                .(self::WOCHENTAGE[$this->monat_wochentag] ?? '?').' im Monat';
        }

        $tage = collect($this->wochentage ?? [])->map(fn ($t) => (int) $t)->sort()->values();

        if ($this->intervall > 1) {
            $namen = $tage->map(fn (int $t) => mb_strtolower(self::WOCHENTAGE[$t] ?? '?').'s');

            return 'alle '.$this->intervall.' Wochen '.$this->aufzaehlung($namen->all());
        }

        return 'jeden '.$this->aufzaehlung($tage->map(fn (int $t) => self::WOCHENTAGE[$t] ?? '?')->all());
    }

    /**
     * Rhythmus + Zeitraum, z. B. „jeden Dienstag vom 01.12.2026 bis 30.06.2027“
     */
    public function beschreibung(): string
    {
        return $this->rhythmusText().' vom '.$this->start->format('d.m.Y').' bis '.$this->end->format('d.m.Y');
    }

    /**
     * @param array<int, string> $teile
     */
    private function aufzaehlung(array $teile): string
    {
        if (count($teile) <= 1) {
            return implode('', $teile);
        }

        $letzter = array_pop($teile);

        return implode(', ', $teile).' und '.$letzter;
    }
}
