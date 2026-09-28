<?php

namespace App\Services\Calendar;

use App\Models\OxTermin;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Models\VertretungsplanWeek;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Brücke zwischen Kalender und Raumplanung.
 *
 * - Raumbelegung (room_bookings) als FullCalendar-Events für einen Zeitraum aufbereiten
 *   (wiederkehrende Stundenplan-Buchungen inkl. A/B-Woche und VP-Stornierungen).
 * - Raumbuchung zu einem Terminverbund anlegen, verschieben und freigeben.
 *
 * Eine Buchung hängt am Terminverbund (room_bookings.ox_verbund_uid), nicht an einer
 * einzelnen Kalender-Kopie – egal in wie vielen Kalendern der Termin steht, der Raum
 * wird genau einmal gebucht.
 */
class KalenderRaumService
{
    /** Farbpalette für Räume (Index = Position des Raums in der Liste) */
    public const RAUM_FARBEN = [
        '#0f766e', '#b45309', '#7c3aed', '#be123c', '#0369a1',
        '#4d7c0f', '#a21caf', '#c2410c', '#1d4ed8', '#047857',
    ];

    public function darfRaeumeSehen(User $user): bool
    {
        return $user->can('view roomBooking');
    }

    public function darfRaeumeBuchen(User $user): bool
    {
        return $user->canAny(['create roomBooking', 'manage rooms']);
    }

    /**
     * Räume für die Kalender-Sidebar inkl. stabiler Anzeigefarbe.
     *
     * @return Collection<int, array{id: int, name: string, nummer: ?string, farbe: string, buchbar: bool}>
     */
    public function raumListe(): Collection
    {
        return Room::query()
            ->orderBy('room_number')
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn (Room $room, int $i) => [
                'id'      => $room->id,
                'name'    => $room->name,
                'nummer'  => $room->room_number,
                'farbe'   => self::RAUM_FARBEN[$i % count(self::RAUM_FARBEN)],
                'buchbar' => (bool) $room->bookable,
            ]);
    }

    // =========================================================================
    // Raumbelegung als Kalender-Events
    // =========================================================================

    /**
     * Belegung der angegebenen Räume im Zeitraum [start, end) als FullCalendar-Events.
     *
     * @param  int[]  $roomIds
     */
    public function belegungEvents(array $roomIds, Carbon $start, Carbon $end): Collection
    {
        if (empty($roomIds)) {
            return collect();
        }

        $farben = $this->raumListe()->pluck('farbe', 'id');
        $rooms  = Room::query()->whereIn('id', $roomIds)->get()->keyBy('id');

        $bookings = RoomBooking::query()
            ->whereIn('room_id', $rooms->keys())
            ->where(function ($q) use ($start, $end) {
                $q->where('is_recurring', true)
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('is_recurring', false)
                            ->where('booking_date', '>=', $start->copy()->startOfDay())
                            ->where('booking_date', '<', $end);
                    });
            })
            ->with('user')
            ->get();

        // VP-Stornierungen: Raum an einem Datum durch den Vertretungsplan freigegeben
        $stornos = $bookings->filter(fn (RoomBooking $b) => $b->cancelled && $b->source === 'indiware_vp');
        $aktiv   = $bookings->reject(fn (RoomBooking $b) => $b->cancelled);

        $events     = collect();
        $wochenTyp  = [];

        for ($tag = $start->copy()->startOfDay(); $tag->lt($end); $tag->addDay()) {
            $wocheKey = $tag->copy()->startOfWeek()->toDateString();
            if (!array_key_exists($wocheKey, $wochenTyp)) {
                $wochenTyp[$wocheKey] = $this->wochenTyp($tag);
            }

            foreach ($aktiv as $booking) {
                if (!$this->findetStattAm($booking, $tag, $wochenTyp[$wocheKey])) {
                    continue;
                }

                if ($booking->is_recurring && $this->istStorniert($booking, $tag, $stornos)) {
                    continue;
                }

                $room = $rooms->get($booking->room_id);
                if (!$room) {
                    continue;
                }

                $events->push($this->alsEvent($booking, $room, $tag, $farben->get($room->id, '#0f766e'), $wochenTyp[$wocheKey]));
            }
        }

        return $events->values();
    }

    /**
     * A/B-Woche laut Vertretungsplan (null = unbekannt → nur Buchungen ohne Wochentyp).
     */
    public function wochenTyp(Carbon $datum): ?string
    {
        return VertretungsplanWeek::query()
            ->where('week', $datum->copy()->startOfWeek()->toDateString())
            ->value('type');
    }

    protected function findetStattAm(RoomBooking $booking, Carbon $tag, ?string $wochenTyp): bool
    {
        if (!$booking->is_recurring) {
            return $booking->booking_date && $booking->booking_date->isSameDay($tag);
        }

        if ((int) $booking->weekday !== $tag->dayOfWeek) {
            return false;
        }

        return $booking->week === null || $booking->week === $wochenTyp;
    }

    protected function istStorniert(RoomBooking $booking, Carbon $tag, Collection $stornos): bool
    {
        return $stornos->contains(fn (RoomBooking $s) => $s->room_id === $booking->room_id
            && $s->booking_date && $s->booking_date->isSameDay($tag)
            && Carbon::parse($s->start)->format('H:i') === Carbon::parse($booking->start)->format('H:i')
            && Carbon::parse($s->end)->format('H:i') === Carbon::parse($booking->end)->format('H:i'));
    }

    protected function alsEvent(RoomBooking $booking, Room $room, Carbon $tag, string $farbe, ?string $wochenTyp): array
    {
        $datum  = $tag->toDateString();
        $beginn = Carbon::parse($datum . ' ' . Carbon::parse($booking->start)->format('H:i'));
        $ende   = Carbon::parse($datum . ' ' . Carbon::parse($booking->end)->format('H:i'));

        $titel = $booking->name ?: 'Belegt';
        if ($booking->klassen) {
            $titel .= ' (' . $booking->klassen . ')';
        }

        return [
            'id'              => 'room_' . $booking->id . '_' . $tag->format('Ymd'),
            'title'           => $room->name . ': ' . $titel,
            'start'           => $beginn->toIso8601String(),
            'end'             => $ende->toIso8601String(),
            'allDay'          => false,
            'color'           => $farbe,
            'editable'        => false,
            'classNames'      => ['cal-event-raum'],
            'extendedProps'   => [
                'isRoomBooking' => true,
                'calendarId'    => 'room_' . $room->id,
                'calendarName'  => $room->name,
                'raum'          => $room->name,
                'buchung'       => $booking->name,
                'klassen'       => $booking->klassen,
                'lehrer'        => $booking->lehrer,
                'gebuchtVon'    => $booking->user?->name,
                'quelle'        => $booking->source,
                'wiederkehrend' => (bool) $booking->is_recurring,
                'raumUrl'       => route('rooms.show.week', [$room, $wochenTyp ?? 'A', $datum]),
            ],
        ];
    }

    // =========================================================================
    // Raumbuchung zu einem Terminverbund
    // =========================================================================

    /**
     * Prüft, ob ein Termin mit Raumbuchung zulässig ist. Wirft ValidationException
     * mit freien Alternativräumen, falls der Raum belegt ist.
     */
    public function pruefeBuchung(User $user, int $roomId, array $termin, ?string $verbundUid = null): Room
    {
        if (!$this->darfRaeumeBuchen($user)) {
            throw ValidationException::withMessages([
                'room_id' => 'Für die Raumbuchung fehlt die Berechtigung.',
            ]);
        }

        $room = Room::query()->where('bookable', true)->find($roomId);
        if (!$room) {
            throw ValidationException::withMessages([
                'room_id' => 'Der gewählte Raum ist nicht buchbar.',
            ]);
        }

        [$beginn, $ende] = $this->zeitraum($termin);

        $kollision = $this->kollision($room, $beginn, $ende, $verbundUid);
        if ($kollision) {
            throw ValidationException::withMessages([
                'room_id' => sprintf(
                    'Der Raum %s ist von %s bis %s bereits belegt (%s). %s',
                    $room->name,
                    Carbon::parse($kollision->start)->format('H:i'),
                    Carbon::parse($kollision->end)->format('H:i'),
                    $kollision->name ?: 'ohne Titel',
                    $this->alternativenText($room, $beginn, $ende)
                ),
            ]);
        }

        return $room;
    }

    /**
     * Prüft, ob ein Termin überhaupt raumbuchungsfähig ist (einzelner Tag, mit Uhrzeit, ohne Wiederholung).
     */
    public function pruefeTerminFuerRaum(array $termin): void
    {
        if (!empty($termin['ganztaegig'])) {
            throw ValidationException::withMessages([
                'room_id' => 'Räume können nur für Termine mit Uhrzeit gebucht werden, nicht für ganztägige.',
            ]);
        }

        if (!empty($termin['rrule'])) {
            throw ValidationException::withMessages([
                'room_id' => 'Für wiederkehrende Termine bitte die Raumbuchung direkt in der Raumplanung anlegen.',
            ]);
        }

        [$beginn, $ende] = $this->zeitraum($termin);
        if (!$beginn->isSameDay($ende)) {
            throw ValidationException::withMessages([
                'room_id' => 'Raumbuchungen sind nur für Termine innerhalb eines Tages möglich.',
            ]);
        }
    }

    /**
     * Legt die Raumbuchung für einen Terminverbund an bzw. verschiebt/ändert sie.
     */
    public function bucheFuerVerbund(string $verbundUid, Room $room, array $termin, User $user): RoomBooking
    {
        [$beginn, $ende] = $this->zeitraum($termin);

        $booking = RoomBooking::query()
            ->where('ox_verbund_uid', $verbundUid)
            ->where('cancelled', false)
            ->first() ?? new RoomBooking(['users_id' => $user->id]);

        $booking->fill([
            'ox_verbund_uid' => $verbundUid,
            'room_id'        => $room->id,
            'weekday'        => $beginn->dayOfWeek,
            'start'          => $beginn->format('H:i'),
            'end'            => $ende->format('H:i'),
            'name'           => 'Termin: ' . $termin['titel'],
            'is_recurring'   => false,
            'booking_date'   => $beginn->copy()->startOfDay(),
            'week'           => null,
            'source'         => 'manual',
            'cancelled'      => false,
        ]);
        $booking->save();

        Log::info('Kalender: Raum gebucht', [
            'verbund_uid' => $verbundUid,
            'room_id'     => $room->id,
            'booking_id'  => $booking->id,
            'user_id'     => $user->id,
        ]);

        return $booking;
    }

    /**
     * Raumbuchung eines Terminverbunds freigeben (Soft-Delete).
     */
    public function freigeben(?string $verbundUid): void
    {
        if (!$verbundUid) {
            return;
        }

        RoomBooking::query()
            ->where('ox_verbund_uid', $verbundUid)
            ->get()
            ->each(fn (RoomBooking $b) => $b->delete());
    }

    /**
     * Nach einem OX-Sync: Raumbuchung an geänderte Zeiten anpassen bzw. freigeben,
     * wenn keine Kopie des Termins mehr existiert. Belegte Räume werden nicht
     * überschrieben – die Abweichung wird nur protokolliert.
     */
    public function abgleichNachSync(string $verbundUid): void
    {
        $booking = RoomBooking::query()
            ->where('ox_verbund_uid', $verbundUid)
            ->where('cancelled', false)
            ->with('room')
            ->first();

        if (!$booking) {
            return;
        }

        $termin = OxTermin::query()->where('verbund_uid', $verbundUid)->orderBy('id')->first();

        if (!$termin) {
            $booking->delete();
            Log::info('Kalender-Sync: Raumbuchung freigegeben, Termin in OX gelöscht', ['booking_id' => $booking->id]);
            return;
        }

        $daten = [
            'titel'      => $termin->titel,
            'beginn'     => $termin->beginn->format('Y-m-d H:i:s'),
            'ende'       => $termin->ende->format('Y-m-d H:i:s'),
            'ganztaegig' => $termin->ganztaegig,
            'rrule'      => $termin->rrule,
        ];

        [$beginn, $ende] = $this->zeitraum($daten);
        $unveraendert = $booking->booking_date?->isSameDay($beginn)
            && Carbon::parse($booking->start)->format('H:i') === $beginn->format('H:i')
            && Carbon::parse($booking->end)->format('H:i') === $ende->format('H:i');

        if ($unveraendert) {
            return;
        }

        try {
            $this->pruefeTerminFuerRaum($daten);

            if ($booking->room && $this->kollision($booking->room, $beginn, $ende, $verbundUid)) {
                throw ValidationException::withMessages(['room_id' => 'Raum zur neuen Zeit belegt.']);
            }

            $booking->update([
                'weekday'      => $beginn->dayOfWeek,
                'start'        => $beginn->format('H:i'),
                'end'          => $ende->format('H:i'),
                'booking_date' => $beginn->copy()->startOfDay(),
                'name'         => 'Termin: ' . $termin->titel,
            ]);
        } catch (ValidationException $e) {
            Log::warning('Kalender-Sync: Raumbuchung konnte nicht nachgezogen werden', [
                'verbund_uid' => $verbundUid,
                'booking_id'  => $booking->id,
                'grund'       => collect($e->errors())->flatten()->first(),
            ]);
        }
    }

    /**
     * Verfügbarkeit aller buchbaren Räume für einen Zeitraum (für die Raumauswahl im Terminformular).
     *
     * @return Collection<int, array{id: int, name: string, nummer: ?string, frei: bool, belegt_durch: ?string}>
     */
    public function verfuegbarkeit(Carbon $beginn, Carbon $ende, ?string $verbundUid = null): Collection
    {
        return Room::query()
            ->where('bookable', true)
            ->orderBy('room_number')
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) use ($beginn, $ende, $verbundUid) {
                $kollision = $this->kollision($room, $beginn, $ende, $verbundUid);

                return [
                    'id'           => $room->id,
                    'name'         => $room->name,
                    'nummer'       => $room->room_number,
                    'frei'         => $kollision === null,
                    'belegt_durch' => $kollision
                        ? sprintf('%s (%s–%s)', $kollision->name ?: 'Belegt',
                            Carbon::parse($kollision->start)->format('H:i'),
                            Carbon::parse($kollision->end)->format('H:i'))
                        : null,
                ];
            })
            ->values();
    }

    // =========================================================================
    // Hilfsmethoden
    // =========================================================================

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function zeitraum(array $termin): array
    {
        return [Carbon::parse($termin['beginn']), Carbon::parse($termin['ende'])];
    }

    protected function kollision(Room $room, Carbon $beginn, Carbon $ende, ?string $verbundUid): ?RoomBooking
    {
        $eigeneId = $verbundUid
            ? RoomBooking::query()->where('ox_verbund_uid', $verbundUid)->where('cancelled', false)->value('id')
            : null;

        return $room->hasBookingCollision(
            $beginn->format('H:i'),
            $ende->format('H:i'),
            $beginn->dayOfWeek,
            $beginn->copy()->startOfDay(),
            $this->wochenTyp($beginn),
            $eigeneId
        );
    }

    protected function alternativenText(Room $belegt, Carbon $beginn, Carbon $ende): string
    {
        $frei = Room::query()
            ->where('bookable', true)
            ->where('id', '!=', $belegt->id)
            ->orderBy('room_number')
            ->orderBy('name')
            ->get()
            ->filter(fn (Room $r) => $this->kollision($r, $beginn, $ende, null) === null)
            ->take(3)
            ->pluck('name');

        return $frei->isEmpty()
            ? 'Es sind keine direkten Alternativräume frei.'
            : 'Freie Alternativen: ' . $frei->implode(', ') . '.';
    }
}
