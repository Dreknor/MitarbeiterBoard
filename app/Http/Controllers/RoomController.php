<?php

namespace App\Http\Controllers;

use App\Http\Requests\createRoomBookingRequest;
use App\Http\Requests\createRoomRequest;
use App\Http\Requests\editRoomRequest;
use App\Http\Requests\ImportRoomsRequest;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\LessonTime;
use App\Models\Zeitraster;
//use Barryvdh\DomPDF\Facade as PDF;
use App\Models\Setting;
use App\Models\VertretungsplanWeek;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravie\Parser\Xml\Reader;
use Laravie\Parser\Xml\Document;
use Illuminate\Http\Request;


class RoomController extends Controller
{


    /**
     * Display a listing of the resource.
     *
     * @return View | RedirectResponse
     */
    public function index()
    {
        if (! auth()->user()->can("view roomBooking")) {
            return redirect(url('/'))->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }



        return view('rooms.rooms.index',[
            'rooms' => Room::query()->orderBy('room_number')->get(),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(createRoomRequest $request)
    {
        if (! auth()->user()->can("manage rooms")) {
            return redirect(url('/'))->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $room = Room::updateOrCreate(
            [
                'name' =>  request('name'),
                'room_number' => request('room_number')
            ],
            ['deleted_at' => null]);

        // Speichere bookable falls gesetzt (Checkbox liefert '1'), Standard: true
        $room->bookable = $request->has('bookable') ? (bool)$request->input('bookable') : true;
        $room->save();

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => "Raum wurde erstellt"
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Room  $room
     * @return View
     */
    public function show(Room $room, $week = null,  $date = null)
    {

        if ($date == null){
            $date = Carbon::today();
        } else {
            $date = Carbon::parse($date);
        }

        $startOfWeek = $date->copy()->startOfWeek();
        $endOfWeek = $date->copy()->endOfWeek();


            $vpWeek = VertretungsplanWeek::query()->where('week', $startOfWeek)->first();
            $week = $vpWeek?->type;

        // Hole alle relevanten Buchungen für diese Woche
        $bookings = RoomBooking::query()
            ->where('room_id', $room->id)
            ->where(function ($query) use ($startOfWeek, $endOfWeek, $week) {
                // Wiederkehrende Buchungen
                $query->where(function ($q) use ($week) {
                    $q->where('is_recurring', true)
                      ->where(function ($subQ) use ($week) {
                          $subQ->whereNull('week')
                               ->orWhere('week', $week);
                      });
                })
                // Oder individuelle Buchungen in dieser Woche
                ->orWhere(function ($q) use ($startOfWeek, $endOfWeek) {
                    $q->where('is_recurring', false)
                      ->whereBetween('booking_date', [$startOfWeek, $endOfWeek]);
                });
            })
            ->get();


        // Formatiere Buchungen für JavaScript
        $bookingsFormatted = $bookings->map(function($booking) use ($startOfWeek, $endOfWeek, $week) {
            $result = [
                'id'           => $booking->id,
                'name'         => $booking->name,
                'klassen'      => $booking->klassen,
                'lehrer'       => $booking->lehrer,
                'start'        => $booking->start,
                'end'          => $booking->end,
                'is_recurring' => $booking->is_recurring,
                'weekday'      => $booking->weekday,
                'week'         => $booking->week,
                'author'       => $booking->user?->name ?? 'Unbekannt',
                'source'       => $booking->source ?? 'manual',
                'cancelled'    => (bool) ($booking->cancelled ?? false),
            ];

            // Für individuelle Buchungen: Berechne Wochentag
            if (!$booking->is_recurring && $booking->booking_date) {
                $result['date']    = $booking->booking_date->format('Y-m-d');
                $result['weekday'] = $booking->booking_date->dayOfWeek;
            }

            return $result;
        });


        $first_booking = $bookings->sortBy('start')->first();
        $last_booking = $bookings->sortByDesc('end')->first();



        return view('rooms.rooms.show', [
            'room' => $room,
            'bookings' => $bookings,
            'bookingsJson' => $bookingsFormatted->toJson(),
            'week' => $week,
            'date' => $date,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
            'first_booking' => $first_booking,
            'last_booking' => $last_booking,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Room  $modelRoom
     * @return View
     */
    public function edit(Room $room)
    {
        return view('rooms.rooms.edit', [
            'room' => $room
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Room  $room
     * @return \Illuminate\Http\Response
     */
    public function update(editRoomRequest $request, Room $room)
    {
        $data = $request->validated();
        // Checkbox sendet nichts, wenn nicht gesetzt => Wert explizit setzen
        $data['bookable'] = $request->has('bookable') ? true : false;
        $room->update($data);
        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Raum wurde aktualisiert'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Room  $room
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Room $room)
    {
        $room->delete();
        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => 'Raum gelöscht'
        ]);
    }

    public function editBooking (RoomBooking $booking){
        return view('rooms.rooms.editBooking', [
            'booking' => $booking,
            'room' => $booking->room
        ]);
    }

    public function updateBooking(createRoomBookingRequest $request, RoomBooking $booking){

        $room = $booking->room;

        try {
            $start = Carbon::parse($request->start);
            $end = Carbon::parse($request->end);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with([
                'type' => 'warning',
                'Meldung' => 'ungültige Start- oder Endzeit'
            ]);
        }


        $isRecurring = $request->is_recurring ?? $booking->is_recurring;
        $bookingDate = $request->booking_date ? Carbon::parse($request->booking_date) : $booking->booking_date;
        $weekday = $isRecurring ? $request->weekday : ($bookingDate ? $bookingDate->dayOfWeek : $booking->weekday);

        if ($request->week != "A" and $request->week != 'B'){
            $week = null;
        } else {
            $week = $request->week;
        }

        // Prüfe auf Kollisionen
        $collision = $room->hasBookingCollision(
            $start,
            $end,
            $weekday,
            !$isRecurring ? $bookingDate : null,
            $isRecurring ? $week : null,
            $booking->id
        );

        if ($collision) {
            $dateInfo = !$isRecurring && $bookingDate ? ' am ' . $bookingDate->format('d.m.Y') : '';
            return redirect()->back()->withInput()->with([
                'type' => 'warning',
                'Meldung'=> 'Raum ist bereits belegt' . $dateInfo
            ]);
        }

        $updateData = [
            'start' => $start->format('H:i'),
            'end' => $end->format('H:i'),
            'name' => $request->name,
            'weekday' => $weekday,
        ];

        if ($isRecurring) {
            $updateData['week'] = $week;
        } else {
            $updateData['booking_date'] = $bookingDate;
        }

        $booking->update($updateData);

        Cache::forget('bookings_'.$room->name);

        return redirect(url('rooms/rooms/'.$room->id))->with([
            'type' => 'success',
            'Meldung'=> 'Buchung aktualisiert'
        ]);

    }

    public function deleteBooking(RoomBooking $booking){
        $room= $booking->room;
        $booking->delete();

        Cache::forget('bookings_'.$room->name);

        return redirect(url('rooms/rooms/'.$room->id))->with([
            'type' => 'success',
            'Meldung'=> 'Buchung gelöscht'
        ]);

    }
    public function storeBooking(createRoomBookingRequest $request){

        $room = Room::find($request->room_id);

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        if ($request->week != "A" and $request->week != 'B'){
            $week = null;
        } else {
            $week = $request->week;
        }

        $isRecurring = $request->is_recurring ?? true;
        $bookingDate = $request->booking_date ? Carbon::parse($request->booking_date) : null;

        // Individuelle Buchung
        if (!$isRecurring && $bookingDate) {
            $weekday = $bookingDate->dayOfWeek;

            // Prüfe auf Kollisionen
            $collision = $room->hasBookingCollision($start, $end, $weekday, $bookingDate, $week);

            if ($collision) {
                return redirect()->back()->withInput()->with([
                    'type' => 'warning',
                    'Meldung'=> 'Raum ist bereits am ' . $bookingDate->format('d.m.Y') . ' belegt'
                ]);
            }

            $booking = new RoomBooking([
                'room_id' => $request->room_id,
                'weekday' => $weekday,
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'name' => $request->name,
                'users_id' => auth()->id(),
                'is_recurring' => false,
                'booking_date' => $bookingDate,
            ]);
            $booking->save();

            Cache::forget('bookings_'.$room->name);

            return redirect()->back()->with([
                'type' => 'success',
                'Meldung'=> 'Einzeltermin für ' . $bookingDate->format('d.m.Y') . ' gebucht'
            ]);
        }

        // Wiederkehrende Buchung
        $weekdays = $request->weekdays ?? [$request->weekday];

        foreach ($weekdays as $weekday){
            // Prüfe auf Kollisionen
            $collision = $room->hasBookingCollision($start, $end, $weekday, null, $week);

            if ($collision) {
                return redirect()->back()->withInput()->with([
                    'type' => 'warning',
                    'Meldung'=> 'Raum ist bereits am ' . config('config.days')[$weekday] . ' belegt'
                ]);
            }

            $booking = new RoomBooking([
                'room_id' => $request->room_id,
                'weekday' => $weekday,
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'week' => $week ?? null,
                'name' => $request->name,
                'users_id' => auth()->id(),
                'is_recurring' => true,
            ]);
            $booking->save();
        }

        Cache::forget('bookings_'.$room->name);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung'=> 'Wiederkehrende Buchung erstellt'
        ]);

    }


    public function export(Room $room)
    {

        $pdf = PDF::loadView('rooms.rooms.room_pdf', [
            'room' => $room
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream();
    }

    public function import (ImportRoomsRequest $request)
    {
        $file = $request->file('file');

        $xml = (new Reader(new Document()))->load($file->getRealPath());


        /*
         * Vorbereitung der Räume
         *
         */
        $raeume = $xml->parse(['raeume' => ['uses' => 'raeume.ra[ra_kurzform,ra_langform]']]) ;

        if ($raeume == null or count($raeume) == 0){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Fehler beim Importieren - keine Räume gefunden'
            ]);
        }

        if ($request->create_rooms == true){
            foreach ($raeume['raeume'] as $raum){
                Room::firstOrCreate(
                    [
                        'room_number' => $raum['ra_kurzform'],
                    ],
                    [
                        'name' => $raum['ra_langform'],
                        'indiware_shortname' => $raum['ra_kurzform'],
                        'bookable' => true,
                    ]
                );
            }
        }

        // Räume aus DB laden – unabhängig davon, ob neue Räume erstellt wurden
        $raumKuerzel = array_column($raeume['raeume'], 'ra_kurzform');
        $rooms = Room::whereIn('room_number', $raumKuerzel)
            ->orWhereIn('indiware_shortname', $raumKuerzel)
            ->get();

        if ($request->deletePlan == true){
            foreach ($rooms as $room){
                $room->bookings()->fromIndiwareXml()->forceDelete();
            }
        }


        /*
         * Vorbereitung der Zeitraster
         *
         */

        try {


            $zeitraster = $xml->parse([
                'woche' => ['uses' => 'grunddaten.g_schulzeitraster.gszr_zeiten.gszr_zeit[::gszr_woche>woche,::gszr_stunde>stunde]'],
                'zeit' => ['uses' => 'grunddaten.g_schulzeitraster.gszr_zeiten.gszr_zeit'],
            ]);

            unset($zeitraster['zeit']['@attributes']);


            $zeiten = [];

            foreach ($zeitraster['zeit'] as $key => $zeit) {
                $zeiten[] = [
                    'zeit' => $zeit,
                    'woche' => $zeitraster['woche'][$key]['woche'],
                    'stunde' => $zeitraster['woche'][$key]['stunde']
                ];
            }
            unset($zeitraster);


        } catch (\Exception $e){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Fehler beim Importieren - keine Zeitraster gefunden'
            ]);
        }

        /*
         * Vorbereitung der Unterrichtseinheiten
         *
         */

        $plan = $xml->parse([
            'unterricht' => ['uses' => 'unterricht.un[un_nummer>id,un_fach>fach,un_klasse>klasse,un_lehrer>lehrer]'],
            'plan' => ['uses' => 'plan.pl[pl_nummer>id,pl_raum>raum,pl_woche>woche,pl_stunde>stunde,pl_tag>tag]'],
        ]);

        // Stundenoffset zwischen Plan und Zeitraster erkennen
        // Indiware kann unterschiedliche Stundennummern verwenden:
        // z.B. Zeitraster: 1-6, Plan: 2-7 → Offset = 1
        $zeitrasterStunden = array_unique(array_column($zeiten, 'stunde'));
        $planStunden = array_unique(array_filter(array_column($plan['plan'], 'stunde'), fn ($s) => $s !== null && $s !== ''));
        $stundenOffset = 0;
        if (count($zeitrasterStunden) > 0 && count($planStunden) > 0) {
            $minZeitraster = (int) min($zeitrasterStunden);
            $minPlan = (int) min($planStunden);
            if ($minPlan !== $minZeitraster) {
                $stundenOffset = $minPlan - $minZeitraster;
                Log::info("Indiware-Import: Stundenoffset erkannt (Plan min={$minPlan}, Zeitraster min={$minZeitraster}, Offset={$stundenOffset})");
            }
        }

        /*
         * Zeitraster in lesson_times synchronisieren
         * Damit der Vertretungsplan-Import (Ak_StundeVon) die korrekten Uhrzeiten verwendet.
         * Die period-Werte nutzen die Plan-Nummerierung (= Ak_StundeVon der VP-API).
         */
        $syncedZeitraster = null;
        if ($request->input('sync_zeitraster')) {
            // Schulname aus XML für Zeitraster-Bezeichnung
            try {
                $kopf = $xml->parse(['schulname' => ['uses' => 'kopf.schulname']]);
                $planBezeichnung = $xml->parse(['p_bezeichnung' => ['uses' => 'plan.p_bezeichnung']]);
                $zeitrasterName = ($kopf['schulname'] ?? 'Indiware') . ' – ' . ($planBezeichnung['p_bezeichnung'] ?? 'Import');
            } catch (\Exception $e) {
                $zeitrasterName = 'Indiware Import ' . now()->format('d.m.Y');
            }

            // Zeitraster-Record erstellen oder vorhandenen verwenden
            if ($request->filled('zeitraster_id')) {
                $syncedZeitraster = Zeitraster::find($request->zeitraster_id);
            }

            if (!$syncedZeitraster) {
                $syncedZeitraster = Zeitraster::create([
                    'name'         => $zeitrasterName,
                    'beschreibung' => 'Automatisch importiert aus Indiware XML am ' . now()->format('d.m.Y H:i'),
                    'ist_standard' => Zeitraster::count() === 0,
                ]);
            }

            // Bestehende LessonTimes dieses Zeitrasters löschen
            $syncedZeitraster->lessonTimes()->delete();

            // Gruppierte Zeitraster-Einträge (pro Woche+Stunde deduplizieren)
            $dauerstunde = 45; // Standard-Stundendauer
            try {
                $grunddaten = $xml->parse(['dauer' => ['uses' => 'grunddaten.g_dauerstunde']]);
                if (!empty($grunddaten['dauer'])) {
                    $dauerstunde = (int) $grunddaten['dauer'];
                }
            } catch (\Exception $e) {}

            $importedPeriods = [];
            foreach ($zeiten as $zeit) {
                $zrStunde = (int) $zeit['stunde'];
                $zrWoche  = $zeit['woche'];

                // Plan-Stundennummer = Zeitraster-Stunde + Offset
                $planPeriod = $zrStunde + $stundenOffset;

                // A/B-Woche mappen (1→A, 2→B), null wenn nur 1 Woche
                $wocheLetter = null;
                if (count(array_unique(array_column($zeiten, 'woche'))) > 1) {
                    $wocheLetter = $zrWoche == 1 ? 'A' : 'B';
                }

                // Duplikat-Check: Wenn beide Wochen identische Zeiten haben → nur einmal ohne Woche speichern
                $dedupKey = $planPeriod . '_' . ($wocheLetter ?? '');
                if (isset($importedPeriods[$dedupKey])) {
                    continue;
                }

                $startTime = Carbon::parse($zeit['zeit']);
                $endTime   = $startTime->copy()->addMinutes($dauerstunde);

                LessonTime::create([
                    'zeitraster_id' => $syncedZeitraster->id,
                    'period'        => $planPeriod,
                    'start'         => $startTime->format('H:i'),
                    'end'           => $endTime->format('H:i'),
                    'week'          => $wocheLetter,
                ]);

                $importedPeriods[$dedupKey] = true;
            }

            // Wenn A- und B-Wochen identische Zeiten haben → zu wochenunabhängig zusammenfassen
            $lessonTimes = $syncedZeitraster->lessonTimes()->get();
            $byPeriod = $lessonTimes->groupBy('period');
            foreach ($byPeriod as $period => $entries) {
                if ($entries->count() === 2) {
                    $a = $entries->firstWhere('week', 'A');
                    $b = $entries->firstWhere('week', 'B');
                    if ($a && $b && $a->start === $b->start && $a->end === $b->end) {
                        $b->delete();
                        $a->update(['week' => null]);
                    }
                }
            }

            Log::info("Indiware-Import: Zeitraster '{$syncedZeitraster->name}' (ID: {$syncedZeitraster->id}) synchronisiert mit " . count($importedPeriods) . " Einträgen");
        }

        $booking = [];
        $updatedIds = [];

        $fehler = [];

        foreach ($plan['plan'] as $key => $pl) {

            if ($pl['raum'] == null || $pl['raum'] === ''){
                continue;
            }

            $room = $rooms->first(function ($room) use ($pl){
                return $room->room_number == $pl['raum'] || $room->indiware_shortname == $pl['raum'];
            });

            if ($room == null){
                continue;
            }

            // Buchungs-Woche (A/B) aus Plan-Eintrag bestimmen
            $buchungsWoche = null;
            if ($pl['woche'] !== '' && $pl['woche'] !== null){
                $buchungsWoche = $pl['woche'] == 1 ? 'A' : 'B';
            }

            // Plan-Stunde auf Zeitraster-Stunde mappen (Offset berücksichtigen)
            $mappedStunde = (int) $pl['stunde'] - $stundenOffset;

            // Passende Startzeit aus Zeitraster ermitteln (zuerst passende Woche, dann Fallback)
            $zeitrasterWoche = ($pl['woche'] !== '' && $pl['woche'] !== null) ? $pl['woche'] : 1;
            $start = null;
            foreach ($zeiten as $zeit){
                if ($zeit['stunde'] == $mappedStunde && $zeit['woche'] == $zeitrasterWoche){
                    $start = Carbon::parse($zeit['zeit']);
                    break;
                }
            }
            // Fallback: beliebige Woche für diese Stunde
            if ($start === null){
                foreach ($zeiten as $zeit){
                    if ($zeit['stunde'] == $mappedStunde){
                        $start = Carbon::parse($zeit['zeit']);
                        break;
                    }
                }
            }
            // Letzter Fallback: ohne Offset versuchen (falls kein Offset nötig war)
            if ($start === null && $stundenOffset !== 0){
                foreach ($zeiten as $zeit){
                    if ($zeit['stunde'] == $pl['stunde'] && $zeit['woche'] == $zeitrasterWoche){
                        $start = Carbon::parse($zeit['zeit']);
                        break;
                    }
                }
            }
            if ($start === null){
                $fehler[] = 'Kein Zeitraster-Eintrag für Stunde ' . $pl['stunde'] . ' (gemappt: ' . $mappedStunde . ')';
                continue;
            }

            $end = $start->copy()->addMinutes(45);

            $unterricht_key = array_search($pl['id'], array_column($plan['unterricht'], 'id'));

            if ($unterricht_key === false){
                continue;
            }

            // Eindeutige source_id aus Plan-Daten generieren
            $sourceId = "pl_{$pl['id']}_{$pl['tag']}_{$pl['stunde']}_{$pl['woche']}";

            // Klassen- und Lehrer-Kürzel extrahieren
            $klasse = $plan['unterricht'][$unterricht_key]['klasse'] ?? '';
            if (is_array($klasse)){
                $klasse = implode(', ', $klasse);
            }

            $lehrer = $plan['unterricht'][$unterricht_key]['lehrer'] ?? '';
            if (is_array($lehrer)){
                $lehrer = implode(', ', $lehrer);
            }

            // Prüfe auf Kollision nur mit NICHT-Indiware-XML-Buchungen (manuelle/VP-Buchungen bleiben geschützt)
            $vergeben = RoomBooking::query()
                ->where('room_id', $room->id)
                ->where('weekday', $pl['tag'])
                ->where(function ($query) use ($start, $end){
                    $query->whereBetween('start', [$start->format('H:i'), $end->format('H:i')]);
                    $query->orWhereBetween('end', [$start->format('H:i'), $end->format('H:i')]);
                })
                ->where('week', $buchungsWoche)
                ->where('source', '!=', 'indiware_xml')
                ->count();

            if ($vergeben > 0){
                $fehler[] = 'Raum '.$room->name.' ist bereits belegt: '.$start->format('H:i').' - '.$end->format('H:i').' am Tag '.$pl['tag'];
            } else {
                $bookingRecord = RoomBooking::updateOrCreate(
                    [
                        'source'    => 'indiware_xml',
                        'source_id' => $sourceId,
                    ],
                    [
                        'weekday'      => $pl['tag'],
                        'start'        => $start->format('H:i'),
                        'end'          => $end->format('H:i'),
                        'users_id'     => auth()->id(),
                        'room_id'      => $room->id,
                        'name'         => trim(($plan['unterricht'][$unterricht_key]['fach'] ?? '') . ' ' . $klasse),
                        'klassen'      => $klasse ?: null,
                        'lehrer'       => $lehrer ?: null,
                        'week'         => $buchungsWoche,
                        'is_recurring' => true,
                    ]
                );

                $updatedIds[] = $bookingRecord->id;
                $booking[] = $bookingRecord;
            }
        }

        // Verwaiste Indiware-XML-Buchungen entfernen (waren im alten Plan, aber nicht mehr im neuen)
        if (!$request->deletePlan) {
            $roomIds = $rooms->pluck('id')->toArray();
            $orphanCount = RoomBooking::fromIndiwareXml()
                ->whereIn('room_id', $roomIds)
                ->when(count($updatedIds) > 0, fn ($q) => $q->whereNotIn('id', $updatedIds))
                ->forceDelete();
        }

        $Meldung = "Import erfolgreich. ";

        if ($request->create_rooms == true){
            $Meldung .= ' '.count($raeume['raeume']) . ' Räume importiert ';
        }

        if ($request->deletePlan == true){
            $Meldung .= 'Alte Indiware-Buchungen gelöscht. ';
        }

        $Meldung .= count($booking) . ' Buchungen importiert/aktualisiert  ';

        if (isset($orphanCount) && $orphanCount > 0) {
            $Meldung .= '(' . $orphanCount . ' veraltete Einträge entfernt) ';
        }

        if ($syncedZeitraster) {
            $syncedCount = $syncedZeitraster->lessonTimes()->count();
            $Meldung .= "Zeitraster \"{$syncedZeitraster->name}\" synchronisiert ({$syncedCount} Stundenzeiten). ";
        }

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => $Meldung,
            'fehler' => $fehler
        ]);



    }

    /**
     * Generate an iCal feed token for a room (admin)
     */
    public function generateFeedToken(Request $request, Room $room)
    {
        if (! auth()->user()->can('manage rooms')) {
            return redirect(url('/'))->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $days = $request->input('expires_in_days');
        $expiresAt = null;
        if ($days && is_numeric($days)) {
            $expiresAt = Carbon::now()->addDays((int)$days);
        }

        $token = $room->generateFeedToken($expiresAt);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Feed-Token generiert',
            'feed_url' => $room->feed_url
        ]);
    }

    /**
     * Revoke current feed token (admin)
     */
    public function revokeFeedToken(Room $room)
    {
        if (! auth()->user()->can('manage rooms')) {
            return redirect(url('/'))->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        $room->revokeFeedToken();

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Feed-Token widerrufen'
        ]);
    }

}
