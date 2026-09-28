<?php

namespace App\Services\Calendar;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\RoomBooking;
use App\Models\User;
use App\Services\OxCalendarService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Termine in mehreren Kalendern gleichzeitig anlegen, ändern und löschen.
 *
 * OX bleibt das führende System: Jede Kalender-Kopie wird als eigenes Event per
 * CalDAV in den jeweiligen OX-Kalender geschrieben (eigene UID, gemeinsame
 * Verbund-Kennung X-MB-VERBUND). Lokal verknüpft ox_termine.verbund_uid die Kopien.
 *
 * Teilfehler (z. B. ein OX-Kalender nicht erreichbar) brechen den Vorgang nicht ab,
 * sondern werden pro Kalender im Ergebnis zurückgemeldet.
 */
class TerminVerbundService
{
    public function __construct(
        protected OxCalendarService $ox,
        protected KalenderRaumService $raum,
    ) {
    }

    /**
     * Termin in allen angegebenen Kalendern anlegen, optional mit Raumbuchung.
     *
     * @param  Collection<int, OxCalendar>  $kalender
     * @param  array  $daten  titel, beschreibung, ort, beginn, ende, ganztaegig, rrule, exdates?
     * @return array{termine: Collection<int, OxTermin>, fehler: array<string, string>, buchung: ?RoomBooking}
     *
     * @throws \Illuminate\Validation\ValidationException wenn der Raum nicht gebucht werden kann
     */
    public function anlegen(User $user, Collection $kalender, array $daten, ?int $roomId = null): array
    {
        $verbundUid = (string) Str::uuid();
        $room       = null;

        // Raum vor dem Schreiben nach OX prüfen – bei Belegung soll gar nichts angelegt werden.
        if ($roomId) {
            $this->raum->pruefeTerminFuerRaum($daten);
            $room = $this->raum->pruefeBuchung($user, $roomId, $daten);
            $daten['ort'] = ($daten['ort'] ?? null) ?: $room->name;
        }

        $termine = collect();
        $fehler  = [];

        foreach ($kalender as $kal) {
            try {
                $termine->push($this->ox->createTermin($kal, array_merge($daten, ['verbund_uid' => $verbundUid])));
            } catch (\RuntimeException $e) {
                $fehler[$kal->name] = $e->getMessage();
                Log::warning('Terminverbund: Anlegen in Kalender fehlgeschlagen', [
                    'kalender' => $kal->name,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $buchung = ($room && $termine->isNotEmpty())
            ? $this->raum->bucheFuerVerbund($verbundUid, $room, $daten, $user)
            : null;

        return ['termine' => $termine, 'fehler' => $fehler, 'buchung' => $buchung];
    }

    /**
     * Termin inkl. aller Kopien aktualisieren.
     *
     * @param  int[]|null  $kalenderIds  Zielkalender; null = bisherige Kalender beibehalten.
     *                                   Neue Kalender erhalten eine Kopie, entfernte werden in OX gelöscht.
     * @param  array  $daten  Termin-Daten; enthält den Schlüssel 'room_id' nur, wenn die
     *                        Raumbuchung geändert werden soll (null = Raum freigeben).
     * @return array{termine: Collection<int, OxTermin>, fehler: array<string, string>, buchung: ?RoomBooking}
     */
    public function aktualisieren(User $user, OxTermin $termin, array $daten, ?array $kalenderIds = null): array
    {
        $termin->loadMissing('kalender');
        $verbund    = $termin->verbund();
        $verbundUid = $termin->verbund_uid ?: (string) Str::uuid();

        $raumAendern = array_key_exists('room_id', $daten);
        $roomId      = $raumAendern ? ($daten['room_id'] ?: null) : null;
        unset($daten['room_id']);

        $bestehendeBuchung = $termin->raumBuchung();
        if (!$raumAendern && $bestehendeBuchung) {
            $roomId = $bestehendeBuchung->room_id;
        }

        $room = null;
        if ($roomId) {
            $this->raum->pruefeTerminFuerRaum($daten);
            $room = $this->raum->pruefeBuchung($user, (int) $roomId, $daten, $termin->verbund_uid);
            $daten['ort'] = ($daten['ort'] ?? null) ?: $room->name;
        }

        $daten['verbund_uid'] = $verbundUid;

        $vorhandeneKalenderIds = $verbund->pluck('ox_calendar_id')->map(fn ($id) => (int) $id);
        $zielIds = $kalenderIds === null
            ? $vorhandeneKalenderIds
            : collect($kalenderIds)->map(fn ($id) => (int) $id)->unique();

        $termine = collect();
        $fehler  = [];

        // 1. Bestehende Kopien aktualisieren bzw. entfernen
        foreach ($verbund as $kopie) {
            $name = $kopie->kalender->name ?? ('Kalender #' . $kopie->ox_calendar_id);

            if (!$this->ox->canEditTermin($user, $kopie->kalender)) {
                if ($kopie->id === $termin->id) {
                    $fehler[$name] = 'Keine Berechtigung zum Bearbeiten.';
                }
                continue;
            }

            try {
                if ($zielIds->contains((int) $kopie->ox_calendar_id)) {
                    $termine->push($this->ox->updateTermin($kopie, $daten));
                } else {
                    $this->ox->deleteTermin($kopie);
                }
            } catch (\RuntimeException $e) {
                $fehler[$name] = $e->getMessage();
            }
        }

        // 2. Neue Kalender: Kopie anlegen
        $neueIds = $zielIds->diff($vorhandeneKalenderIds);
        if ($neueIds->isNotEmpty()) {
            $neueKalender = OxCalendar::query()->whereIn('id', $neueIds)->with('groups')->get();

            foreach ($neueKalender as $kal) {
                if (!$this->ox->canWriteCalendar($user, $kal)) {
                    $fehler[$kal->name] = 'Keine Schreibberechtigung.';
                    continue;
                }

                try {
                    $termine->push($this->ox->createTermin($kal, $daten));
                } catch (\RuntimeException $e) {
                    $fehler[$kal->name] = $e->getMessage();
                }
            }
        }

        // 3. Raumbuchung nachziehen
        $buchung = null;
        if ($room && $termine->isNotEmpty()) {
            $buchung = $this->raum->bucheFuerVerbund($verbundUid, $room, $daten, $user);
        } elseif (($raumAendern && !$roomId) || !$this->verbundExistiert($verbundUid)) {
            $this->raum->freigeben($verbundUid);
        }

        return ['termine' => $termine, 'fehler' => $fehler, 'buchung' => $buchung];
    }

    /**
     * Termin (Drag & Drop) auf eine neue Zeit verschieben – alle Kopien und die Raumbuchung ziehen mit.
     */
    public function verschieben(User $user, OxTermin $termin, string $beginn, string $ende, bool $ganztaegig): array
    {
        return $this->aktualisieren($user, $termin, [
            'titel'        => $termin->titel,
            'beschreibung' => $termin->beschreibung,
            'ort'          => $termin->ort,
            'beginn'       => $beginn,
            'ende'         => $ende,
            'ganztaegig'   => $ganztaegig,
            'rrule'        => $termin->rrule,
        ]);
    }

    /**
     * Termin löschen – nur diese Kopie oder alle Kopien des Verbunds.
     * Die Raumbuchung wird freigegeben, sobald keine Kopie mehr existiert.
     *
     * @return array{geloescht: int, fehler: array<string, string>}
     */
    public function loeschen(User $user, OxTermin $termin, bool $alleKopien = true): array
    {
        $termin->loadMissing('kalender');
        $ziele = $alleKopien ? $termin->verbund() : collect([$termin]);

        $geloescht = 0;
        $fehler    = [];

        foreach ($ziele as $kopie) {
            $name = $kopie->kalender->name ?? ('Kalender #' . $kopie->ox_calendar_id);

            if (!$this->ox->canEditTermin($user, $kopie->kalender)) {
                if ($kopie->id === $termin->id) {
                    $fehler[$name] = 'Keine Berechtigung zum Löschen.';
                }
                continue;
            }

            try {
                $this->ox->deleteTermin($kopie);
                $geloescht++;
            } catch (\RuntimeException $e) {
                $fehler[$name] = $e->getMessage();
            }
        }

        if ($termin->verbund_uid && !$this->verbundExistiert($termin->verbund_uid)) {
            $this->raum->freigeben($termin->verbund_uid);
        }

        return ['geloescht' => $geloescht, 'fehler' => $fehler];
    }

    protected function verbundExistiert(string $verbundUid): bool
    {
        return OxTermin::query()->where('verbund_uid', $verbundUid)->exists();
    }

    /**
     * Fehlerliste als lesbarer Text für Flash-Meldungen.
     *
     * @param  array<string, string>  $fehler
     */
    public static function fehlerText(array $fehler): string
    {
        return collect($fehler)
            ->map(fn ($msg, $kalender) => $kalender . ': ' . $msg)
            ->implode('; ');
    }
}
