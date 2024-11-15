<?php

namespace App\Http\Controllers;

use App\Http\Requests\createRoomBookingRequest;
use App\Http\Requests\createRoomRequest;
use App\Http\Requests\editRoomRequest;
use App\Http\Requests\ImportRoomsRequest;
use App\Models\Room;
use App\Models\RoomBooking;
//use Barryvdh\DomPDF\Facade as PDF;
use App\Models\VertretungsplanWeek;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
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

        if ($week == null){
            $week = VertretungsplanWeek::query()->where('week', \Carbon\Carbon::now()->startOfWeek())->first()->type;
        }

        $bookings = RoomBooking::query()
            ->where('room_id', $room->id)
            ->where(function ($query) use ($date){
                $query->whereNull('date');
                $query->orWhereDate('date', ">=", $date->format('Y-m-d'));
            })
            ->where(function ($query) use ($week){
                $query->whereNull('week');
                $query->orWhere('week', $week);
            })
            ->get();

        return view('rooms.rooms.show', [
            'room' => $room,
            'bookings' => $bookings,
            'week' => $week,
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

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        for ($x = $start->copy(); $x->lessThanOrEqualTo($end); $x->addMinutes(15)){
            if ($room->hasBooking($request->weekday, $x->copy()->addMinute()->format('H:i')) and $room->hasBooking($request->weekday, $x->copy()->addMinute()->format('H:i'))->id != $booking->id){
                return redirect()->back()->with([
                    'type' => 'warning',
                    'Meldung'=> 'Raum ist bereits belegt'
                ]);
            }
        }

        $booking->update($request->validated());

        Cache::forget('bookings_'.$room->name);

        return redirect(url('rooms/rooms/'.$room->id))->with([
            'type' => 'success',
            'Meldung'=> 'Raum gebucht'
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

        for ($x = $start->copy(); $x->lessThanOrEqualTo($end); $x->addMinutes(15)){
            if ($room->hasBooking($request->weekday, $x->copy()->addMinute()->format('H:i'))){
                return redirect()->back()->with([
                   'type' => 'warning',
                   'Meldung'=> 'Raum ist bereits belegt'
                ]);
            }
        }

        $booking = new RoomBooking($request->validated());
        $booking->users_id = auth()->id();
        $booking->save();

        Cache::forget('bookings_'.$room->name);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung'=> 'Raum gebucht'
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
                    'week' => isset($woche) ? $woche : null
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
