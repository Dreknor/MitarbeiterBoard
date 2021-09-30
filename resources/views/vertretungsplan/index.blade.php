<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{csrf_token()}}">

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
<body id="app-layout">
<div class="main-panel" style='width: 100%; background-color: #f4f3ef; background-image: url("{!! asset('img/'.config('config.show_background')) !!}")'>
<div class="content">
        @for($x=Carbon\Carbon::today(); $x< $targetDate; $x->addDay())
            @if(!$x->isWeekend())
                 <div class="card">
                    <div class="card-header" id="heading{{$x->format('Ymd')}}">
                        <h6>
                             Vertretungen für <div class="text-danger d-inline">{{$x->locale('de')->dayName}} </div>, den {{$x->format('d.m.Y')}}
                        </h6>
                    </div>
                    <div id="collapse{{$x->format('Ymd')}}"  aria-labelledby="heading{{$x->format('Ymd')}}" >
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Stunde</th>
                                    <th>Klasse</th>
                                    <th>Fächer</th>
                                    <th>Lehrer</th>
                                    <th>Kommentar</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($vertretungen->filter(function ($vertretung) use ($x) {
                                    if ($vertretung->date->eq($x)){
                                        return $vertretung;
                                    }
                                }) as $vertretung)
                                    <tr>
                                        <td>{{$vertretung->date->format('d.m.Y')}}</td>
                                        <td>{{$vertretung->stunde}}</td>
                                        <td>{{$vertretung->klasse->name}}</td>
                                        <td>{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                                        <td>{{optional($vertretung->lehrer)->name}}</td>
                                        <td>{{$vertretung->comment}}</td>
                                    </tr>
                                @endforeach
                                @foreach($news->filter(function ($news) use ($x) {
                                    if (($news->date_start->lessThanOrEqualTo($x) and $news->date_end == null) or ($news->date_start->lessThanOrEqualTo($x) and $news->date_end->greaterThanOrEqualTo($x))){
                                        return $news;
                                    }
                                }) as $dailyNews)
                                    <tr>
                                        <td colspan="6">
                                            {{$dailyNews->news}}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                    </div>
                    </div>
                </div>
            @endif
        @endfor
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
