<tr class="@if(!$day->isWeekday() or is_holiday($day)) bg-gradient-radial-info @endif" id="{{$day->format('Y-m-d')}}">
    <td>
        {{$day->dayName}}, {{$day->format('d.m.Y')}}
    </td>
    <td>
        @foreach($timesheet_days->filterDay($day) as $timesheet_day)
            @if(!is_null($timesheet_day->start))
                <div class="row">
                    <div class="col">
                        {{$timesheet_day?->start?->format('H:i')}} - {{$timesheet_day?->end?->format('H:i')}} Uhr
                    </div>
                </div>
            @endif
        @endforeach
    </td>
    <td>
        @if($timesheet_days->filterDay($day)->sum('pause') > 0)
            {{$timesheet_days->filterDay($day)->sum('pause')}} Minuten
        @endif
    </td>
    <td>
        {{convertTime($timesheet_days->filterDay($day)->sum('duration'))}}
        h @if($day->isWeekday() and !is_holiday($day))
            / {{convertTime(percent_to_seconds($employe->employments_date($day)->sum('percent'))/5)}} h
        @endif
    </td>
    <td>
        @foreach($timesheet_days->filterDay($day) as $timesheet_day)
            <div class="row">
                <div class="col">
                    {{$timesheet_day?->comment}}
                </div>
            </div>
        @endforeach
        @if(is_holiday($day))
            {{is_holiday($day)->title}}
        @endif
    </td>
    <td class="@if($timesheet_days->filterDay($day)->sum('duration')-percent_to_seconds($employe->employments_date($day)->sum('percent'))/5 > 0) text-success @else text-danger @endif">
        @if($day->isWeekday() and !is_holiday($day))
            {{convertTime($timesheet_days->filterDay($day)->sum('duration')-percent_to_seconds($employe->employments_date($day)->sum('percent'))/5)}}
        @else
            @if($timesheet_days->filterDay($day)->sum('duration') > 0)
                {{$balance +=  $timesheet_days->filterDay($day)->sum('duration')}}
                {{convertTime($timesheet_days->filterDay($day)->sum('duration'))}}
            @endif
        @endif
    </td>
    @if(!$timesheet->is_locked)
        <td>
            <div class="container-fluid">
                <div class="row">
                    <div class="col">
                        <a href="{{url('timesheets/'.$employe->id.'/'.$timesheet->id.'/'.$day->format('Y-m-d').'/add')}}"
                           class="btn btn-sm btn-bg-gradient-x-blue-green">
                            <i class="fa fa-plus"></i>
                        </a>
                    </div>
                    <div class="col">
                        @if($timesheet_days->filterDay($day)->count()>1)
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-danger dropdown-toggle" data-toggle="dropdown"
                                        id="dropdownMenuButton_{{$day->format('Y-m-d')}}">
                                    <i class="fa fa-trash "></i>
                                </button>
                                <div class="dropdown-menu">
                                    @foreach($timesheet_days->filterDay($day) as $timesheet_day)
                                        <a href='{{url('timesheets/'.$employe->id.'/'.$timesheet->id.'/'.$timesheet_day->id.'/delete')}}'
                                           class="dropdown-item text-danger">
                                            @if($timesheet_day->percent_of_workingtime != null)
                                                {{$timesheet_day?->comment}} löschen
                                            @else
                                                {{$timesheet_day?->start?->format('H:i')}}
                                                - {{$timesheet_day?->end?->format('H:i')}} Uhr löschen
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @elseif($timesheet_days->filterDay($day)->count() == 1)
                            <a href="{{url('timesheets/'.$employe->id.'/'.$timesheet->id.'/'.$timesheet_days->filterDay($day)->first()->id.'/delete')}}"
                               class="btn btn-sm btn-outline-danger">
                                <i class="fa fa-trash"></i>
                            </a>
                        @endif
                    </div>
                    <div class="col">
                        <div class="dropleft">
                            <button class="btn btn-sm btn-bg-gradient-x-blue-green dropdown-toggle"
                                    data-toggle="dropdown" id="dropdownMenuButton_item_{{$day->format('Y-m-d')}}">
                                <i class="fa fa-scroll"></i>
                            </button>
                            <ul class="dropdown-menu">
                                @foreach(config('config.abwesenheiten_arbeitszeit') as $key => $absence)
                                    <li>
                                        <a href="{{url('timesheets/'.$employe->id.'/'.$timesheet->id.'/'.$day->format('Y-m-d').'/addFromAbsence/'.$key)}}"
                                           class="dropdown-item text-info">
                                            {{$key}}
                                        </a>
                                    </li>

                                @endforeach
                            </ul>
                        </div>

                    </div>
                </div>
            </div>
        </td>
    @endif
</tr>
