<?php

namespace App\Services;

use App\Models\LessonTime;
use App\Models\Room;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RoomBookingFromVpService
{
    private array $summary = [
        'room_bookings_created'   => 0,
        'room_bookings_cancelled' => 0,
        'room_bookings_skipped'   => 0,
        'room_conflicts'          => [],
        'missing_rooms'           => [],
    ];

    private ?array $roomCache = null;

    private ?Collection $lessonTimeCache = null;

    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * TODO-3A: Entfernt alle VP-Buchungen für ein bestimmtes Datum (forceDelete für Idempotenz).
     */
    public function clearVpBookingsForDate(Carbon $date): int
    {
        return RoomBooking::withTrashed()
            ->fromVertretungsplan()
            ->whereDate('booking_date', $date)
            ->forceDelete();
    }

    /**
     * Verarbeitet eine einzelne Aktion aus dem Vertretungsplan.
     * Muss AUSSERHALB der $klassen-Schleife aufgerufen werden.
     */
    public function processAktion(object $aktion, Carbon $date, ?string $week = null): void
    {
        if (!(settings('vp_room_integration_enabled') ?? true)) {
            return;
        }

        $art = $aktion->Ak_Art ?? '';
        $stundenAnz = max(1, (int) ($aktion->Ak_StundenAnz ?? 1));

        try {
            switch ($art) {
                case 'Ausf.':
                    $this->handleAusfall($aktion, $date, $stundenAnz, $week);
                    break;
                case 'Änd.':
                case 'Ã„nd.': // alternatives Encoding
                    $this->handleAenderung($aktion, $date, $stundenAnz, $week);
                    break;
                case 'Verl.':
                    $this->handleVerlegung($aktion, $date, $stundenAnz, $week);
                    break;
                case 'Neu':
                    $this->handleNeu($aktion, $date, $stundenAnz, $week);
                    break;
                default:
                    Log::info("VP-Raum: Unbekannte/nicht unterstützte Aktionsart '{$art}' (Ak_Id: " . ($aktion->Ak_Id ?? 'n/a') . ")");
                    $this->summary['room_bookings_skipped']++;
            }
        } catch (\Throwable $e) {
            Log::error('VP-Raum: Fehler bei processAktion', [
                'ak_id' => $aktion->Ak_Id ?? null,
                'art'   => $art,
                'date'  => $date->format('d.m.Y'),
                'error' => $e->getMessage(),
            ]);
            $this->summary['room_bookings_skipped']++;
        }
    }

    // ─── Handler ─────────────────────────────────────────────────────────────

    /**
     * Ausfall: Unterricht fällt aus → regulären Raum als frei markieren.
     * Erstellt einen Stornierungseintrag (cancelled=true).
     */
    private function handleAusfall(object $aktion, Carbon $date, int $stundenAnz, ?string $week): void
    {
        $raumKuerzel = $aktion->Raeume[0] ?? null;
        if (!$raumKuerzel) {
            $this->summary['room_bookings_skipped']++;
            return;
        }

        $room = $this->findRoom($raumKuerzel);
        if (!$room) {
            return;
        }

        $times = $this->resolveTimeRangeCached($aktion->Ak_StundeVon, $stundenAnz, $week);
        if (!$times) {
            $this->summary['room_bookings_skipped']++;
            return;
        }

        // TODO-3B: create() statt updateOrCreate()
        RoomBooking::create([
            'source'       => 'indiware_vp',
            'source_id'    => ($aktion->Ak_Id ?? md5($raumKuerzel . $date->format('Ymd') . $aktion->Ak_StundeVon)) . '_cancel',
            'room_id'      => $room->id,
            'start'        => $times['start'],
            'end'          => $times['end'],
            'name'         => '⊘ Ausfall: ' . ($aktion->Ak_Fach ?? '?'),
            'booking_date' => $date,
            'is_recurring' => false,
            'cancelled'    => true,
            'weekday'      => $date->dayOfWeek,
            'users_id'     => null,
        ]);

        $this->invalidateRoomCache($room);
        $this->summary['room_bookings_cancelled']++;
    }

    /**
     * Änderung: Vertretung im gleichen oder anderen Raum.
     * Bei Raumwechsel: alten Raum stornieren, neuen belegen.
     */
    private function handleAenderung(object $aktion, Carbon $date, int $stundenAnz, ?string $week): void
    {
        $raumAlt = $aktion->Raeume[0] ?? null;
        $raumNeu = $aktion->VRaeume[0] ?? $raumAlt;

        if (!$raumAlt && !$raumNeu) {
            $this->summary['room_bookings_skipped']++;
            return;
        }

        $times = $this->resolveTimeRangeCached($aktion->Ak_StundeVon, $stundenAnz, $week);
        if (!$times) {
            $this->summary['room_bookings_skipped']++;
            return;
        }

        // Raumwechsel? → alten Raum freigeben
        if ($raumAlt && $raumNeu && $raumAlt !== $raumNeu) {
            $roomAlt = $this->findRoom($raumAlt);
            if ($roomAlt) {
                RoomBooking::create([
                    'source'       => 'indiware_vp',
                    'source_id'    => ($aktion->Ak_Id ?? md5($raumAlt . $date->format('Ymd'))) . '_cancel',
                    'room_id'      => $roomAlt->id,
                    'start'        => $times['start'],
                    'end'          => $times['end'],
                    'name'         => '⊘ VP-Raumwechsel',
                    'booking_date' => $date,
                    'is_recurring' => false,
                    'cancelled'    => true,
                    'weekday'      => $date->dayOfWeek,
                    'users_id'     => null,
                ]);
                $this->invalidateRoomCache($roomAlt);
                $this->summary['room_bookings_cancelled']++;
            }
        }

        // Neuen Raum belegen
        if ($raumNeu) {
            $roomNeu = $this->findRoom($raumNeu);
            if ($roomNeu) {
                $fach    = $aktion->Ak_VFach ?? $aktion->Ak_Fach ?? '?';
                $klassen = implode(', ', $aktion->Klassen ?? []);

                // Konfliktprüfung (manuelle oder XML-Buchungen haben Vorrang)
                $conflict = $roomNeu->hasBookingCollision(
                    $times['start'], $times['end'],
                    $date->dayOfWeek, $date, $week
                );

                if ($conflict && $conflict->source !== 'indiware_vp') {
                    $this->recordConflict($roomNeu, $date, $times, $conflict, $aktion);
                }

                RoomBooking::create([
                    'source'       => 'indiware_vp',
                    'source_id'    => (string) ($aktion->Ak_Id ?? md5($raumNeu . $date->format('Ymd') . $aktion->Ak_StundeVon)),
                    'room_id'      => $roomNeu->id,
                    'start'        => $times['start'],
                    'end'          => $times['end'],
                    'name'         => "VP: {$fach}" . ($klassen ? " ({$klassen})" : ''),
                    'booking_date' => $date,
                    'is_recurring' => false,
                    'cancelled'    => false,
                    'weekday'      => $date->dayOfWeek,
                    'users_id'     => null,
                ]);
                $this->invalidateRoomCache($roomNeu);
                $this->summary['room_bookings_created']++;
            }
        }
    }

    /**
     * Verlegung: Unterricht von Datum/Stunde A nach Datum/Stunde B verlegt.
     * Original-Slot freigeben, Ziel-Slot belegen.
     */
    private function handleVerlegung(object $aktion, Carbon $date, int $stundenAnz, ?string $week): void
    {
        $times = $this->resolveTimeRangeCached($aktion->Ak_StundeVon, $stundenAnz, $week);
        if (!$times) {
            $this->summary['room_bookings_skipped']++;
            return;
        }

        // Originaldatum/-stunde freigeben
        $raumAlt = $aktion->Raeume[0] ?? null;
        if ($raumAlt) {
            $roomAlt  = $this->findRoom($raumAlt);
            $datumVon = isset($aktion->Ak_DatumVon)
                ? Carbon::createFromFormat('d.m.Y', $aktion->Ak_DatumVon)
                : $date;

            if ($roomAlt) {
                RoomBooking::create([
                    'source'       => 'indiware_vp',
                    'source_id'    => ($aktion->Ak_Id ?? md5($raumAlt . $datumVon->format('Ymd'))) . '_cancel',
                    'room_id'      => $roomAlt->id,
                    'start'        => $times['start'],
                    'end'          => $times['end'],
                    'name'         => '⊘ Verlegt',
                    'booking_date' => $datumVon,
                    'is_recurring' => false,
                    'cancelled'    => true,
                    'weekday'      => $datumVon->dayOfWeek,
                    'users_id'     => null,
                ]);
                $this->invalidateRoomCache($roomAlt);
                $this->summary['room_bookings_cancelled']++;
            }
        }

        // Zieldatum/-stunde belegen
        $raumNeu = $aktion->VRaeume[0] ?? $raumAlt;
        if ($raumNeu && isset($aktion->Ak_DatumNach)) {
            $roomNeu    = $this->findRoom($raumNeu);
            $datumNach  = Carbon::createFromFormat('d.m.Y', $aktion->Ak_DatumNach);
            $stundeNach = $aktion->Ak_StundeNach ?? $aktion->Ak_StundeVon;
            $timesNach  = $this->resolveTimeRangeCached((int) $stundeNach, $stundenAnz, $week);

            if ($roomNeu && $timesNach) {
                $fach    = $aktion->Ak_VFach ?? $aktion->Ak_Fach ?? '?';
                $klassen = implode(', ', $aktion->Klassen ?? []);

                RoomBooking::create([
                    'source'       => 'indiware_vp',
                    'source_id'    => (string) ($aktion->Ak_Id ?? md5($raumNeu . $datumNach->format('Ymd') . $stundeNach)),
                    'room_id'      => $roomNeu->id,
                    'start'        => $timesNach['start'],
                    'end'          => $timesNach['end'],
                    'name'         => "VP (verl.): {$fach}" . ($klassen ? " ({$klassen})" : ''),
                    'booking_date' => $datumNach,
                    'is_recurring' => false,
                    'cancelled'    => false,
                    'weekday'      => $datumNach->dayOfWeek,
                    'users_id'     => null,
                ]);
                $this->invalidateRoomCache($roomNeu);
                $this->summary['room_bookings_created']++;
            }
        }
    }

    /**
     * Neu: Zusätzlicher Unterricht ohne regulären Stundenplan-Slot.
     */
    private function handleNeu(object $aktion, Carbon $date, int $stundenAnz, ?string $week): void
    {
        $raumNeu = $aktion->VRaeume[0] ?? ($aktion->Raeume[0] ?? null);
        if (!$raumNeu) {
            $this->summary['room_bookings_skipped']++;
            return;
        }

        $room = $this->findRoom($raumNeu);
        if (!$room) {
            return;
        }

        $stundeNach = $aktion->Ak_StundeNach ?? $aktion->Ak_StundeVon;
        $times      = $this->resolveTimeRangeCached((int) $stundeNach, $stundenAnz, $week);
        if (!$times) {
            $this->summary['room_bookings_skipped']++;
            return;
        }

        $fach    = $aktion->Ak_Fach ?? '?';
        $klassen = implode(', ', $aktion->Klassen ?? []);

        RoomBooking::create([
            'source'       => 'indiware_vp',
            'source_id'    => (string) ($aktion->Ak_Id ?? md5($raumNeu . $date->format('Ymd') . $stundeNach)),
            'room_id'      => $room->id,
            'start'        => $times['start'],
            'end'          => $times['end'],
            'name'         => "VP (neu): {$fach}" . ($klassen ? " ({$klassen})" : ''),
            'booking_date' => $date,
            'is_recurring' => false,
            'cancelled'    => false,
            'weekday'      => $date->dayOfWeek,
            'users_id'     => null,
        ]);
        $this->invalidateRoomCache($room);
        $this->summary['room_bookings_created']++;
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    /**
     * Nutzt einen In-Memory-Cache mit nur 1 DB-Query (statt 3).
     */
    private function findRoom(string $kuerzel): ?Room
    {
        if ($this->roomCache === null) {
            $allRooms = Room::all();
            $this->roomCache = [
                'by_shortname' => $allRooms->whereNotNull('indiware_shortname')->keyBy('indiware_shortname'),
                'by_number'    => $allRooms->whereNotNull('room_number')->keyBy('room_number'),
            ];
        }

        $room = $this->roomCache['by_shortname'][$kuerzel]
            ?? $this->roomCache['by_number'][$kuerzel]
            ?? null;

        if (!$room) {
            if (!in_array($kuerzel, $this->summary['missing_rooms'])) {
                $this->summary['missing_rooms'][] = $kuerzel;
            }
            $this->summary['room_bookings_skipped']++;
        }

        return $room;
    }

    /**
     * Spezifische Woche (A/B) hat Vorrang vor generischem Eintrag (week=null).
     */
    private function resolveTimeRangeCached(int $period, int $count = 1, ?string $week = null): ?array
    {
        if ($this->lessonTimeCache === null) {
            $this->lessonTimeCache = LessonTime::all();
        }

        $startLesson = $this->lessonTimeCache
            ->where('period', $period)
            ->sortBy(fn($lt) => $lt->week === null ? 1 : 0) // spezifische Woche zuerst
            ->first(fn($lt) => $lt->week === $week || $lt->week === null);

        if (!$startLesson) {
            Log::warning("LessonTime: Keine Zeitangabe für Stunde {$period} (Woche: {$week})");
            return null;
        }

        if ($count <= 1) {
            return ['start' => $startLesson->start, 'end' => $startLesson->end];
        }

        $endPeriodNum = $period + $count - 1;
        $endLesson    = $this->lessonTimeCache
            ->where('period', $endPeriodNum)
            ->sortBy(fn($lt) => $lt->week === null ? 1 : 0)
            ->first(fn($lt) => $lt->week === $week || $lt->week === null);

        return [
            'start' => $startLesson->start,
            'end'   => $endLesson ? $endLesson->end : $startLesson->end,
        ];
    }

    private function invalidateRoomCache(Room $room): void
    {
        Cache::forget('bookings_' . $room->name);
    }

    private function recordConflict(Room $room, Carbon $date, array $times, RoomBooking $conflict, object $aktion): void
    {
        $conflictData = [
            'room'        => $room->name,
            'date'        => $date->format('d.m.Y'),
            'time'        => $times['start'] . '-' . $times['end'],
            'conflicting' => $conflict->name,
            'ak_id'       => $aktion->Ak_Id ?? null,
        ];

        $this->summary['room_conflicts'][] = $conflictData;

        Log::warning('VP-Raum: Raumkonflikt erkannt', $conflictData);
    }
}

