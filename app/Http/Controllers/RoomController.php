<?php

namespace App\Http\Controllers;

use App\Http\Requests\createRoomBookingRequest;
use App\Http\Requests\createRoomRequest;
use App\Http\Requests\editRoomRequest;
use App\Http\Requests\ImportRoomsRequest;
use App\Models\Room;
use App\Models\RoomBooking;
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

        $settings = Setting::query()->where('module', 'Raumplan')->get();

        $time_start = Carbon::createFromFormat('H:i', request('start'));
        $time_end = Carbon::createFromFormat('H:i' , request('end'));

        if ($time_start->hour < $settings->where('setting', 'room_booking_start')->first()->value or
            $time_end->hour > $settings->where('setting', 'room_booking_end')->first()->value) {
            return redirect()->back()->withInput()->with([
                'type' => 'warning',
                'Meldung' => 'Buchungszeit liegt außerhalb der erlaubten Zeiten ('.$settings->where('setting', 'room_booking_start')->first()->value.':00 - '.$settings->where('setting', 'room_booking_end')->first()->value.':00)'
            ]);
        }

        $room = Room::updateOrCreate(
            [
                'name' =>  request('name'),
                'room_number' => request('room_number')
            ],
            ['deleted_at' => null]);
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
                'id' => $booking->id,
                'name' => $booking->name,
                'start' => $booking->start,
                'end' => $booking->end,
                'is_recurring' => $booking->is_recurring,
                'weekday' => $booking->weekday,
                'week' => $booking->week,
            ];

            // Für individuelle Buchungen: Berechne Wochentag
            if (!$booking->is_recurring && $booking->booking_date) {
                $result['date'] = $booking->booking_date->format('Y-m-d');
                $result['weekday'] = $booking->booking_date->dayOfWeek;
            }

            return $result;
        });

        $settings = Setting::query()->where('module', 'Raumplan')->get();

        $settingsArray = [];
        foreach ($settings as $setting) {
            $settingsArray[$setting->setting_name] = $setting->value;
        }

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
            'settings' => $settingsArray,
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
        $room->update($request->validated());
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
                Room::updateOrCreate(
                    [
                        'room_number' => $raum['ra_kurzform'],
                        'name' => $raum['ra_langform'],
                        'indiware_shortname' => $raum['ra_kurzform']
                    ],
                    ['deleted_at' => null]
                );
            }

        }

        $rooms = Room::whereIn('room_number', array_column($raeume['raeume'], 'ra_kurzform'))->orWhereIn('indiware_shortname', array_column($raeume['raeume'], 'ra_kurzform'))->get();

        if ($request->deletePlan == true){
            foreach ($rooms as $room){
                $room->bookings()->whereNull('date')->orWhere('date', '<=', now())->delete();
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
            'unterricht' => ['uses' => 'unterricht.un[un_nummer>id,un_fach>fach,un_klasse>klasse]'],
            'plan' => ['uses' => 'plan.pl[pl_nummer>id,pl_raum>raum,pl_woche>woche,pl_stunde>stunde,pl_tag>tag]'],
        ]);

        $booking = [];

        $fehler = [];

        foreach ($plan['plan'] as $key => $pl) {

            if ($pl['raum'] == null){
                continue;
            }

            $room = $rooms->filter(function ($room) use ($pl){
                return $room->room_number == $pl['raum'] or $room->indiware_shortname == $pl['raum'];
            })->first();

            if ($room == null){
                continue;
            }

            if (isset($zeiten[$pl['stunde']]['woche'])){
                $woche = $zeiten[$pl['stunde']]['woche'];
            } else {
                $woche = 1;
            }

            foreach ($zeiten as $zeit){
                if ($zeit['stunde'] == $pl['stunde']-1 and $zeit['woche'] == $woche){
                    $start = Carbon::parse($zeit['zeit']);
                }
            }

            unset($woche);

            $end = $start->copy()->addMinutes(45);

            $unterricht_key = array_search($pl['id'], array_column($plan['unterricht'], 'id'));

            $vergeben = RoomBooking::query()
                ->where('room_id', $room->id)
                ->where('weekday', $pl['tag'])
                ->where(function ($query) use ($start, $end){
                    $query->whereBetween('start', [$start->format('H:i'), $end->format('H:i')]);
                    $query->orWhereBetween('end', [$start->format('H:i'), $end->format('H:i')]);
                })
                ->where('week', $pl['woche'])
                ->count();

            if ($vergeben > 0){
                $fehler[] = 'Raum '.$room->name.' ist bereits belegt: '.$start->format('H:i').' - '.$end->format('H:i').' am Tag '.$pl['tag'];
            } else {

                if ($pl['woche'] != null){
                    $woche = $pl['woche'] == 1 ? 'A' : 'B';
                }

                $booking[] = [
                    'weekday' => $pl['tag'],
                    'start' => $start->format('H:i'),
                    'end' => $end->format('H:i'),
                    'users_id' => auth()->id(),
                    'room_id' => $room->id,
                    'name' => $plan['unterricht'][$unterricht_key]['fach'] . ' ' . $plan['unterricht'][$unterricht_key]['klasse'],
                    'week' => isset($woche) ? $woche : null,
                    'is_recurring' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        RoomBooking::insert($booking);

        $Meldung = "Import erfolgreich. ";

        if ($request->create_rooms == true){
            $Meldung .= ' '.count($raeume['raeume']) . ' Räume importiert ';
        }

        if ($request->deletePlan == true){
            $Meldung .= 'alter Plan gelöscht.';
        }

        $Meldung .= count($booking) . ' Buchungen importiert  ';


        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => $Meldung,
            'fehler' => $fehler
        ]);



    }

}
