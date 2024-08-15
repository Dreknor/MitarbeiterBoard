

<td class="@if(!is_null($holiday) and $holiday->approved and !is_holiday($day) and !$day->isWeekend()) bg-gradient-directional-success
            @elseif(!is_null($holiday) and $holiday->rejected and !is_holiday($day) and !$day->isWeekend()) bg-gradient-directional-danger
            @elseif(!is_null($holiday) and !$holiday->approved and !is_holiday($day) and !$day->isWeekend()) bg-gradient-directional-amber
            @else
                @if(is_holiday($day) or $day->isWeekend())  bg-info
                @elseif(is_ferien($day)) bg-gradient-x-light-blue
               @endif
            @endif border-right text-center">
    @if(!is_null($holiday) and !is_holiday($day) and !$day->isWeekend())
        @if($holiday->approved)
            <i class="fa fa-check"></i>
        @elseif($holiday->rejected)
            <i class="fas fa-times"></i>
        @else
            <i class="fa fa-question"></i>
        @endif
    @endif
</td>
