<?php

namespace App\Http\Controllers;

use App\Http\Requests\createRoomBookingRequest;
use App\Http\Requests\createRoomRequest;
use App\Http\Requests\editRoomRequest;
use App\Models\Room;
use App\Models\RoomBooking;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

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

        $free_rooms = Room::whereDoesntHave('bookings', function (Builder $query){
            $query->where('weekday', Carbon::now()->subDay()->weekday())->where('start', '<=', Carbon::now()->format('H:i:s'))->where('end', '>', Carbon::now()->format('H:i:s'));
        })->get();


        return view('rooms.rooms.index',[
            'rooms' => Room::query()->orderBy('name')->get(),
            'free_rooms' => $free_rooms
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
    public function show(Room $room, $date = null)
    {

        if ($date == null){
            $date = Carbon::today();
        } else {
            $date = Carbon::parse($date);
        }

        $bookings = RoomBooking::query()
            ->where('room_id', $room->id)
            ->where(function ($query) use ($date){
                $query->whereNull('date');
                $query->orWhereDate('date', ">=", $date->format('Y-m-d'));
            })
            ->get();

        return view('rooms.rooms.show', [
            'room' => $room,
            'bookings' => $bookings
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
        //
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
/*
        return view('rooms.rooms.room_pdf', [
            'room' => $room
        ]);
*/
        $pdf = PDF::loadView('rooms.rooms.room_pdf', [
            'room' => $room
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream();
    }

}
