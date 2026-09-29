<?php

namespace App\Services\Personal\Zeit;

use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use Carbon\Carbon;

/**
 * Kommen/Gehen-Buchung für Terminal und Dashboard.
 *
 * Offene Buchung heute → wird mit "jetzt" geschlossen (Gehen), sonst neue Buchung (Kommen).
 * Die gesetzliche Mindestpause (§ 4 ArbZG) wird beim Gehen automatisch eingetragen.
 */
class TimeRecordingService
{
    public const KOMMEN = 'kommen';
    public const GEHEN = 'gehen';

    public function __construct(private readonly TimesheetService $timesheets)
    {
    }

    /**
     * @return array{0: TimesheetDays, 1: string}
     */
    public function stempeln(User $user, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $timesheet = $this->timesheets->forMonth($user, $now);

        $offen = $timesheet->timesheet_days()
            ->whereDate('date', $now->toDateString())
            ->whereNotNull('start')
            ->whereNull('end')
            ->whereNull('percent_of_workingtime')
            ->orderByDesc('start')
            ->first();

        if ($offen !== null) {
            $minuten = $offen->start->copy()->setDateFrom($now)->diffInMinutes($now);
            $offen->update([
                'end' => $now->format('H:i:s'),
                'pause' => $offen->pause ?: self::gesetzlichePause($minuten),
            ]);
            $this->timesheets->recalculate($timesheet, true, true);

            return [$offen->fresh(), self::GEHEN];
        }

        $day = new TimesheetDays([
            'date' => $now->toDateString(),
            'start' => $now->format('H:i:s'),
            'comment' => 'digitale Zeiterfassung',
        ]);
        $day->timesheet_id = $timesheet->id;
        $day->save();

        return [$day, self::KOMMEN];
    }

    public function istEingestempelt(User $user, ?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return TimesheetDays::query()
            ->whereHas('timesheet', fn ($q) => $q->where('employe_id', $user->id))
            ->whereDate('date', $now->toDateString())
            ->whereNotNull('start')
            ->whereNull('end')
            ->whereNull('percent_of_workingtime')
            ->exists();
    }

    /**
     * Offene Buchung vom Vortag (vergessenes Ausstempeln).
     */
    public function offenVomVortag(User $user, ?Carbon $now = null): ?TimesheetDays
    {
        $now ??= Carbon::now();

        return TimesheetDays::query()
            ->whereHas('timesheet', fn ($q) => $q->where('employe_id', $user->id))
            ->whereDate('date', $now->copy()->subDay()->toDateString())
            ->whereNotNull('start')
            ->whereNull('end')
            ->whereNull('percent_of_workingtime')
            ->first();
    }

    /**
     * Mindestpause nach § 4 ArbZG in Minuten.
     */
    public static function gesetzlichePause(int $arbeitsMinuten): ?int
    {
        if ($arbeitsMinuten > 9 * 60) {
            return 45;
        }
        if ($arbeitsMinuten > 6 * 60) {
            return 30;
        }

        return null;
    }
}
