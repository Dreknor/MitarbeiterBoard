<?php

namespace App\Services\Rooms;

use App\Models\LessonTime;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Zeitraster;
use App\Services\Calendar\KalenderRaumService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Sucht freie Räume für einen Zeitraum an einem Datum.
 *
 * Berücksichtigt wie die Kalender-Raumbelegung:
 * - Einzeltermine am Datum,
 * - wiederkehrende Buchungen am Wochentag inkl. A/B-Woche laut Vertretungsplan
 *   (ist die Woche unbekannt, zählen A- und B-Buchungen als belegt),
 * - Stornierungen aus dem Vertretungsplan (Raum an dem Tag freigegeben),
 * - stornierte Buchungen zählen nie als Belegung.
 *
 * Nur buchbare Räume werden durchsucht.
 */
class RaumSucheService
{
    public function __construct(private KalenderRaumService $kalenderRaum)
    {
    }

    /**
     * @return array{
     *     wochen_typ: ?string,
     *     frei: Collection<int, array{room: Room, frei_ab: ?string, frei_bis: ?string}>,
     *     belegt: Collection<int, array{room: Room, buchungen: Collection<int, RoomBooking>}>
     * }
     */
    public function suche(Carbon $datum, string $von, string $bis): array
    {
        $datum     = $datum->copy()->startOfDay();
        $von       = Carbon::parse($von)->format('H:i');
        $bis       = Carbon::parse($bis)->format('H:i');
        $wochenTyp = $this->kalenderRaum->wochenTyp($datum);

        $raeume = Room::query()
            ->where('bookable', true)
            ->orderBy('room_number')
            ->orderBy('name')
            ->get();

        $buchungenJeRaum = $this->buchungenAm($raeume->modelKeys(), $datum, $wochenTyp)->groupBy('room_id');

        $frei   = collect();
        $belegt = collect();

        foreach ($raeume as $raum) {
            $buchungen = $buchungenJeRaum->get($raum->id, collect())
                ->sortBy(fn (RoomBooking $b) => $this->zeit($b->start))
                ->values();

            $kollisionen = $buchungen
                ->filter(fn (RoomBooking $b) => $von < $this->zeit($b->end) && $bis > $this->zeit($b->start))
                ->values();

            if ($kollisionen->isNotEmpty()) {
                $belegt->push(['room' => $raum, 'buchungen' => $kollisionen]);
                continue;
            }

            $frei->push([
                'room'     => $raum,
                // Ende der letzten Buchung davor bzw. Beginn der nächsten danach (null = ganzer Tag frei)
                'frei_ab'  => $buchungen->map(fn ($b) => $this->zeit($b->end))->filter(fn ($e) => $e <= $von)->max(),
                'frei_bis' => $buchungen->map(fn ($b) => $this->zeit($b->start))->filter(fn ($s) => $s >= $bis)->min(),
            ]);
        }

        return [
            'wochen_typ' => $wochenTyp,
            'frei'       => $frei,
            'belegt'     => $belegt,
        ];
    }

    /**
     * Stunden des Standard-Zeitrasters für die Schnellauswahl (passend zur A/B-Woche).
     *
     * @return Collection<int, array{stunde: int, von: string, bis: string}>
     */
    public function stunden(?string $wochenTyp = null): Collection
    {
        $zeitraster = Zeitraster::getStandard();

        $query = LessonTime::query()
            ->when($zeitraster, fn ($q) => $q->where('zeitraster_id', $zeitraster->id),
                fn ($q) => $q->whereNull('zeitraster_id'));

        return $query->get()
            // Wochenspezifische Zeiten haben Vorrang vor wochenunabhängigen
            ->filter(fn (LessonTime $lt) => $lt->week === null || $lt->week === $wochenTyp)
            ->sortBy(fn (LessonTime $lt) => $lt->week === null ? 1 : 0)
            ->unique('period')
            ->sortBy('period')
            ->map(fn (LessonTime $lt) => [
                'stunde' => (int) $lt->period,
                'von'    => $this->zeit($lt->start),
                'bis'    => $this->zeit($lt->end),
            ])
            ->values();
    }

    /**
     * Aktive Buchungen der Räume, die am Datum stattfinden (VP-Stornierungen herausgerechnet).
     *
     * @param  int[]  $roomIds
     * @return Collection<int, RoomBooking>
     */
    protected function buchungenAm(array $roomIds, Carbon $datum, ?string $wochenTyp): Collection
    {
        if (empty($roomIds)) {
            return collect();
        }

        $buchungen = RoomBooking::query()
            ->whereIn('room_id', $roomIds)
            ->where(function ($q) use ($datum, $wochenTyp) {
                $q->where(function ($q) use ($datum) {
                    $q->where('is_recurring', false)
                        ->whereDate('booking_date', $datum->toDateString());
                })->orWhere(function ($q) use ($datum, $wochenTyp) {
                    $q->where('is_recurring', true)
                        ->where('weekday', $datum->dayOfWeek)
                        // A/B-Woche unbekannt → vorsichtshalber Buchungen beider Wochen als Belegung werten
                        ->when($wochenTyp !== null, fn ($q) => $q->where(function ($q) use ($wochenTyp) {
                            $q->whereNull('week')->orWhere('week', $wochenTyp);
                        }));
                });
            })
            ->get();

        $stornos = $buchungen->filter(fn (RoomBooking $b) => $b->cancelled && $b->source === 'indiware_vp');

        return $buchungen
            ->reject(fn (RoomBooking $b) => $b->cancelled)
            ->reject(fn (RoomBooking $b) => $b->is_recurring && $stornos->contains(
                fn (RoomBooking $s) => $s->room_id === $b->room_id
                    && $this->zeit($s->start) === $this->zeit($b->start)
                    && $this->zeit($s->end) === $this->zeit($b->end)
            ))
            ->values();
    }

    protected function zeit(?string $wert): string
    {
        return Carbon::parse($wert)->format('H:i');
    }
}
