@php
    // Helper-Funktion für Feiertage oder Wochenenden
    $isHolidayOrWeekend = function($day) {
        return !$day->isWeekday() || is_holiday($day);
    };
@endphp

<table class="table table-hover border table-responsive-sm">
    <thead>
    <tr>
        <th class="border-right">Name</th>
        <!-- Optimierte Darstellung der Tage -->
        @for($x = $startOfTable->copy(); $x->lessThanOrEqualTo($endOfTable); $x->addDay())
            <th class="text-center border-right {{ $isHolidayOrWeekend($x) ? 'bg-info' : '' }}">
                {{ $x->day }}
            </th>
        @endfor
    </tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr class="@foreach($user->groups_rel as $group) {{ $group->name }} @endforeach">
            <!-- Benutzername -->
            <td class="border-right w-25">
                {{ $user->name }}
            </td>
            <!-- Tage des Benutzers -->
            @for($x = $startOfTable->copy(); $x->lessThanOrEqualTo($endOfTable); $x->addDay())
                @include('personal.holidays.partials.table-cell', [
                    'holiday' => $holidays->firstWhere(fn($holiday) =>
                        $holiday->start_date->lessThanOrEqualTo($x) &&
                        $holiday->end_date->greaterThanOrEqualTo($x) &&
                        $holiday->employe_id === $user->id &&
                        !$holiday->rejected
                    ),
                    'day' => $x,
                ])
            @endfor
        </tr>
    @endforeach
    </tbody>
</table>
