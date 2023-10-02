<table class="table table-hover border table-responsive-sm">
    <thead>
    <tr>
        <th class="border-right">Name</th>
        @for($x = $startOfTable->copy(); $x->lessThanOrEqualTo($endOfTable); $x->addDay())
            <th class="text-center border-right @if(!$x->isWeekday() or is_holiday($x)) bg-info @endif" >
                {{$x->day}}
            </th>
        @endfor
    </tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td class="border-right w-25">
                {{$user->name}}
            </td>
            @for($x = $startOfTable->copy(); $x->lessThanOrEqualTo($endOfTable); $x->addDay())
                        @include('personal.holidays.partials.table-cell', [
                            'holiday' => $holidays->filter(function($holiday) use ($x, $user) {
                                return $holiday->start_date->lessThanOrEqualTo($x) and $holiday->end_date->greaterThanOrEqualTo($x) and $holiday->employe_id == $user->id;
                            })->first(),
                            'day' => $x
                        ])
            @endfor
        </tr>

    @endforeach
    </tbody>
</table>
