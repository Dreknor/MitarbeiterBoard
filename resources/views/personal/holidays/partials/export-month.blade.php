<table class="table border" style="break-inside: auto">
    <thead>
    <tr>
        <td colspan="{{$month->diffInDays($month->copy()->endOfMonth())+1}}">
            {{$month->monthName}} {{$month->year}}
        </td>
    </tr>
    <tr>
        <th class="border-right">Name</th>
        @for($x = $month->copy(); $x->lessThanOrEqualTo($month->copy()->endOfMonth()); $x->addDay())
            <th class="text-center border-right @if(!$x->isWeekday() or is_holiday($x)) bg-info @endif" >
                {{$x->day}}
            </th>
        @endfor
    </tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td class="border-right">
                {{$user->vorname}}
            </td>
            @for($day = $month->copy(); $day->lessThanOrEqualTo($month->copy()->endOfMonth()); $day->addDay())
                @php($holiday = $holidays->filter(function($holiday) use ($day, $user) {
                                return $holiday->start_date->lessThanOrEqualTo($day) and $holiday->end_date->greaterThanOrEqualTo($day) and $holiday->employe_id == $user->id;
                            })->first())

                <td class="@if(!is_null($holiday) and $holiday->approved and !is_holiday($day) and !$day->isWeekend()) bg-light
                            @elseif(!is_null($holiday) and !$holiday->approved and !is_holiday($day) and !$day->isWeekend())
                            @else
                                @if(is_holiday($day) or $day->isWeekend())  bg-info
                                @elseif(is_ferien($day)) bg-light-gray

                               @endif
                            @endif border-right text-center">
                    @if(!is_null($holiday) and !is_holiday($day) and !$day->isWeekend())
                        @if($holiday->approved)
                            U
                        @else
                            U (?)
                        @endif
                    @else
                        {{$holiday}}
                    @endif
                </td>
            @endfor
        </tr>

    @endforeach
    </tbody>
</table>
