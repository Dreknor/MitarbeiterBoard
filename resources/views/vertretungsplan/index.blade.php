<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{csrf_token()}}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="refresh" content="600">


    <link rel="shortcut icon" href="{{asset('img/favicon.ico')}}" type="image/x-icon">

    <title>{{env('APP_NAME')}}</title>

    <!-- CSS Files -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet" />
    <link href="{{asset('css/paper-dashboard.css?v=2.0.0')}}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />

    <!--<script src="https://kit.fontawesome.com/c8f58e3eb6.js"></script>-->
    <link href="{{asset('/css/all.css')}}" rel="stylesheet"> <!--load all styles -->

    @stack('css')

</head>
<body>
<div class="bg-secondary" style='width: 100%; height: 100%; background-color: #f4f3ef; background-image: url("{!! asset('img/'.settings('show_background')) !!}")'>
<div class="content d-none d-lg-block">

        @if($startDate->greaterThan(\Carbon\Carbon::now()))
        <div class="card border border-dark">
            <div class="card-header" id="heading{{\Carbon\Carbon::now()->format('Ymd')}}">
                <h6>
                    Vertretungen für <div class="text-danger d-inline">{{\Carbon\Carbon::now()->locale('de')->dayName}} </div>, den {{\Carbon\Carbon::now()->format('d.m.Y')}} @if($weeks->count() > 0 and $weeks->where('week', \Carbon\Carbon::now()->startOfWeek())->first() != null) ({{$weeks->where('week', \Carbon\Carbon::now()->startOfWeek())->first()->type}} - Woche) @endif
                </h6>

                    <div class="pull-right">
                        abgerufen: {{\Carbon\Carbon::now()->format('d.m.Y H:i')}}
                    </div>

            </div>
            <div id="collapse{{\Carbon\Carbon::now()->format('Ymd')}}"  aria-labelledby="heading{{\Carbon\Carbon::now()->format('Ymd')}}" >
                <div class="card-body">
                    <div class="">
                        <table class="table table-bordered">

                            <tbody>

                            @foreach($news->filter(function ($news) {
                                if ($news->isActive(\Carbon\Carbon::now())){
                                     return $news;
                                }
                            }) as $dailyNews)
                                <tr>
                                    <th colspan="6" class="border-outline-info">
                                        {{$dailyNews->news}}
                                    </th>
                                </tr>
                            @endforeach
                            @if(!is_null($absences))
                                <tr>
                                    <th colspan="6">
                                        @if($absences->count() > 1)
                                            Es fehlen:
                                        @else
                                            Es fehlt:
                                        @endif
                                            @foreach($absences as $absence)
                                                @if($absence->start_date->lessThanOrEqualTo(\Carbon\Carbon::today()) and $absence->end_date->gte(\Illuminate\Support\Carbon::today()))
                                                    {{$absence->user->shortname}} @if($absence->reason != "") ({{$absence->reason}}) @endif @if(!$loop->last),@endif
                                                @endif
                                            @endforeach
                                    </th>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @for($x=$startDate; $x< $targetDate; $x->addDay())
            @if(!$x->isWeekend())
                 <div class="card border border-dark">
                    <div class="card-header" id="heading{{$x->format('Ymd')}}">
                        <h6>
                             Vertretungen für <div class="text-danger d-inline">{{$x->locale('de')->dayName}} </div>, den {{$x->format('d.m.Y')}} @if($weeks->count() > 0 and $weeks->where('week', $x->copy()->startOfWeek())->first() != null) ({{$weeks->where('week', $x->copy()->startOfWeek())->first()->type}} - Woche) @endif
                        </h6>
                        @if($x == \Carbon\Carbon::today())
                            <div class="pull-right">
                                abgerufen: {{\Carbon\Carbon::now()->format('d.m.Y H:i')}}
                            </div>
                        @endif
                    </div>
                    <div id="collapse{{$x->format('Ymd')}}"  aria-labelledby="heading{{$x->format('Ymd')}}" >
                        <div class="card-body">
                            <div class="">
                                <table class="table table-bordered">
                                <thead  class="thead-light">
                                    <tr class="">
                                        <th class="d-lg-table-cell">Klasse</th>
                                        <th class="d-lg-table-cell">Stunde</th>
                                        <th class="d-lg-table-cell">Fächer</th>
                                        <th class="d-none d-lg-table-cell">Lehrer</th>
                                        <th class="d-none d-lg-table-cell">Kommentar</th>
                                    </tr>
                                    <tr class="d-lg-none">
                                        <th class="d-lg-table-cell">Lehrer</th>
                                        <th class="d-lg-table-cell" colspan="2">Kommentar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($vertretungen->sortBy('klasse.name')->filter(function ($vertretung) use ($x) {
                                    if ($vertretung->date->eq($x)){
                                        return $vertretung;
                                    }
                                }) as $vertretung)
                                    <tr @if(($loop->iteration-1)%2 == 0) class="bg-secondary text-white" @endif>
                                        <td class="d-lg-table-cell">{{$vertretung->klasse->name}}</td>
                                        <td class="d-lg-table-cell">{{$vertretung->stunde}}</td>
                                        <td class="d-lg-table-cell">{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                                        <td class="d-none d-lg-table-cell">{{optional($vertretung->lehrer)->shortname}}</td>
                                        <td class="d-none d-lg-table-cell">{{$vertretung->comment}}</td>
                                    </tr>
                                    <tr class="d-lg-none @if(($loop->iteration-1)%2 == 0) bg-secondary text-white @endif">
                                        <td class="d-lg-table-cell">{{optional($vertretung->lehrer)->shortname}}</td>
                                        <td class="d-lg-table-cell" colspan="2">{{$vertretung->comment}}</td>
                                    </tr>
                                @endforeach
                                <tr class="">

                                </tr>
                                @foreach($news->filter(function ($news) use ($x) {
                                    if ($news->isActive($x)){
                                         return $news;
                                    }
                                }) as $dailyNews)
                                    <tr>
                                        <th colspan="6" class="border-outline-info">
                                            {{$dailyNews->news}}
                                        </th>
                                    </tr>
                                @endforeach
                                @if(!is_null($absences) and $absences->count() > 0)
                                    <tr>
                                        <th colspan="5">
                                            @if($absences->count() > 1)
                                                Es fehlen:
                                            @else
                                                Es fehlt:
                                            @endif
                                                @foreach($absences as $absence)
                                                    @if($absence->start_date->lessThanOrEqualTo($x->copy()->endOfDay()) and $absence->end_date->gte($x->copy()->startOfDay()))
                                                        {{$absence->user->shortname}} @if($absence->reason != "") ({{$absence->reason}}) @endif @if(!$loop->last),@endif
                                                    @endif
                                              @endforeach
                                        </th>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                            </div>
                    </div>
                    </div>
                </div>
            @endif
        @endfor
</div>

    <div class="content d-block d-lg-none">
        @include('vertretungsplan.vertretungMobil')
    </div>
</div>




<script src="{{asset('js/core/jquery.min.js')}}"></script>
<script src="{{asset('js/core/jquery-ui.min.js')}}"></script>
<script src="{{asset('js/core/popper.min.js')}}"></script>
<script src="{{asset('js/core/bootstrap.min.js')}}"></script>
<script src="{{asset('js/plugins/perfect-scrollbar.jquery.min.js')}}"></script>


<!-- Chart JS
    <script src="{{asset('js/plugins/chartjs.min.js')}}"></script>
    -->

<!--  Notifications Plugin    -->
<script src="{{asset('js/plugins/bootstrap-notify.js')}}"></script>

<!-- Control Center for Now Ui Dashboard: parallax effects, scripts for the example pages etc -->
<script src="{{asset('js/paper-dashboard.min.js?v=2.0.0')}}"></script>
</body>
</html>
