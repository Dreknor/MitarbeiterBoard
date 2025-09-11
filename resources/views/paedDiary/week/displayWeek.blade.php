@extends('paedDiary.week.layout')
@section('content')
    <table id="week-table" class="table table-bordered table-striped bg-light">
        <thead>
            <tr>
                <th>
                    Klasse
                </th>
                <th>
                    Montag
                </th>
                <th>
                    Dienstag
                </th>
                <th>
                    Mittwoch
                </th>
                <th>
                    Donnerstag
                </th>
                <th>
                    Freitag
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($klassen as $klasse)
                <tr style="background-color: {{$klasse->color}}; color: {{$klasse->text_color}}">
                    <th>
                        {{$klasse->name}}
                    </th>
                    @for($x=1;$x<6;$x++)
                        <td>
                            @if(array_key_exists($klasse->id, $appointmentsByDay) and (is_array($appointmentsByDay[$klasse->id])))
                                @if(array_key_exists($x, $appointmentsByDay[$klasse->id]))
                                    @include('paedDiary.week.day', ['appointments' => $appointmentsByDay[$klasse->id][$x]])

                                @endif
                            @endif
                        </td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
