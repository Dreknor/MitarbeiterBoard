<html>
    <head>
        <style>
            @php( include(public_path().'/css/bootstrap.min.css'))
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <div class="card w-100">
                <div class="card-header">
                    <h5>
                        {{$room->name}} ({{$room->room_number}})
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th></th>
                            <th class="border-right">
                                Montag
                            </th>
                            <th class="border-right">
                                Dienstag
                            </th>
                            <th class="border-right">
                                Mittwoch
                            </th>
                            <th class="border-right">
                                Donnerstag
                            </th>
                            <th class="border-right">
                                Freitag
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @for($time = \Carbon\Carbon::createFromTimeString(config('rooms.start_booking')); $time->lessThanOrEqualTo(\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))); $time->addMinutes(15))
                            <tr style="height: 10px">
                                <td class="border-right border-bottom pt-0 pb-0 text-center"  style="height: 10px; font-size: 10px">
                                        {{$time->format('H:i')}}
                                </td>
                                @for($x = 1; $x<6; $x++)
                                    @if($room->hasBooking($x, $time->format('H:i')) and $room->hasBooking($x, $time->format('H:i'))->start == $time->format('H:i:00'))
                                        <td class="border-right border-bottom text-white text-center bg-info"
                                            rowspan="{{ceil($room->hasBooking($x, $time->format('H:i'))->duration/15)+1}}"
                                            onclick="location.href='{{url('rooms/booking/'.$room->hasBooking($x, $time->format('H:i'))->id)}}'">
                                            {{$room->hasBooking($x, $time->format('H:i'))->name}}
                                        </td>
                                    @elseif($room->hasBooking($x, $time->format('H:i')) == null)
                                        <td class="border-right border-bottom"  style="height: 10px">

                                        </td>
                                    @endif
                                @endfor
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>
