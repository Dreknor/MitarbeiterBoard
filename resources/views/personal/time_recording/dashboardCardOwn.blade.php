<div class="card">
    <div class="card-header text-white bg-gradient-directional-blue">
        <h5>
            {{$card->title}}
        </h5>
    </div>
    <div class="card-body">
        <div class="container-fluid">
            <table class="table table-striped">
                @for($x = \Carbon\Carbon::now()->startOfWeek(); $x->lessThanOrEqualTo(\Carbon\Carbon::now()->endOfWeek()); $x->addDay())
                    <tr>
                        <td class="border-right w-50">
                            {{$x->locale('de')->dayName}} {{$x->format('d.m.Y')}}
                        </td>
                        <td class="w-50">
                            @if(array_key_exists($x->format('Y-m-d'), $days))
                                    @foreach($days[$x->format('Y-m-d')] as $timesheetDay)
                                        @if(!is_null($timesheetDay->start) or !is_null($timesheetDay->start) )
                                            {{$timesheetDay?->start?->format('H:i')}} - {{$timesheetDay?->end?->format('H:i')}} Uhr<br>
                                        @elseif(!is_null($timesheetDay->percent_of_workingtime))
                                            {{$timesheetDay->comment}}<br>
                                       @endif

                                    @endforeach
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endfor
            </table>
        </div>
    </div>
    <div class="card-footer border-top">
        <div class="row">
            <div class="col">
                @if($logout == 1)
                    <a href="{{url('timesheets/'.auth()->id().'/logout')}}" class="btn btn-bg-gradient-x-blue-green">Abmelden</a>
                @else
                    <a href="{{url('timesheets/'.auth()->id().'/login')}}" class="btn btn-bg-gradient-x-blue-green">Anmelden</a>
                @endif
            </div>
            <div class="col">
                <div class="float-right">
                    <a href="{{url('timesheets/'.auth()->id())}}" class="btn btn-primary">zum Arbeitszeitnachweis</a>
                </div>
        </div>
    </div>
</div>
