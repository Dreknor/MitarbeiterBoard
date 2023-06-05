<div class="card border-left" style="max-width: 10%;">
    <div @class(['card-header','border-top','border-bottom']) style="height: 45px;">

        Zeit

    </div>
    <div @class(['card-body', 'border-bottom'])  style="max-height: 50px; min-height: 50px;">

    </div>
    <div @class(['card-body','p-0','m-0', ])  style="height: 534px;">
        <ul @class(['time'])>

            @for($time=$day->copy()->setHour(8)->setMinute(0); $time->format('H:i') < '14:30'; $time->addMinutes(15))
                <li @class('leererTermin leererTermin_'.$time->minute.' ')>
                    {{$time->format('H:i')}}
                </li>
            @endfor
        </ul>
    </div>
    <div @class(['card-footer', 'border-bottom', 'border-top'])   style="max-height: 60px; min-height: 60px;">
        &nbsp;
    </div>
</div>

