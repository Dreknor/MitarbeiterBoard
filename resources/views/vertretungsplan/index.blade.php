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
<div class="main-panel" style="width: 100%; background-color: #f4f3ef;">
<div class="content">
    <div id="accordion">
        <div class="card">
            <div class="card-header" id="headingOne">
                <h6>
                    <button class="btn btn-link" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        <i class="far fa-caret-square-down"></i> Vertretungen für {{\Carbon\Carbon::today()->format('d.m.Y')}}
                    </button>
                </h6>
            </div>
            <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordion">
                <div class="card-body d-none d-md-block">
                        <table class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Stunde</th>
                                <th>Klasse</th>
                                <th>Lehrer</th>
                                <th>Kommentar</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($vertretungen_heute as $vertretung)
                                <tr>
                                    <td>{{$vertretung->date->format('d.m.Y')}}</td>
                                    <td>{{$vertretung->stunde}}</td>
                                    <td>{{$vertretung->klasse->name}}</td>
                                    <td>{{optional($vertretung->lehrer)->name}}</td>
                                    <td>{{$vertretung->comment}}</td>
                                </tr>
                            @endforeach
                            @foreach($news_heute as $news)
                                <tr>
                                    <td colspan="6">
                                        {{$news->news}}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                </div>
                <div class="card-body d-block d-md-none">
                        <table class="table table-bordered">
                            <thead>
                            <tr class="bg-success">
                                <th rowspan="3">Datum</th>
                                <th>Stunde</th>
                                <th>Klasse</th>
                            </tr>
                            <tr class="bg-success">
                                <th>Lehrer</th>
                                <th>Fächer</th>
                            </tr>
                            <tr class="bg-success">
                                <th colspan="2">Kommentar</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($vertretungen_heute as $vertretung)
                                <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                    <td rowspan="3">{{$vertretung->date->format('d.m.Y')}}</td>
                                    <td>{{$vertretung->stunde}}</td>
                                    <td>{{$vertretung->klasse->name}}</td>
                                </tr>
                                <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                    <td>{{optional($vertretung->lehrer)->kuerzel}}</td>
                                    <td>{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                                </tr>
                                <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                    <td colspan="2">{{$vertretung->comment}}</td>
                                </tr>
                            @endforeach
                            @foreach($news_heute as $news)
                                <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                    <td colspan="6">
                                        {{$news->news}}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header" id="headingTwo">
                <h6 class="mb-0">
                    <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        <i class="far fa-caret-square-down"></i> Vertretungen für {{\Carbon\Carbon::tomorrow()->format('d.m.Y')}}
                    </button>
                </h6>
            </div>
            <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#accordion">
                <div class="card-body d-none d-md-block">
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
                            @foreach($vertretungen_morgen as $vertretung)
                                <tr>
                                    <td>{{$vertretung->date->format('d.m.Y')}}</td>
                                    <td>{{$vertretung->stunde}}</td>
                                    <td>{{$vertretung->klasse->name}}</td>
                                    <td>{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                                    <td>{{optional($vertretung->lehrer)->kuerzel}}</td>
                                    <td>{{$vertretung->comment}}</td>
                                </tr>
                            @endforeach
                            @foreach($news_morgen as $news)
                                <tr>
                                    <td colspan="6">
                                        {{$news->news}}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                </div>
                <div class="card-body d-block d-md-none">
                    <table class="table table-bordered">
                        <thead>
                        <tr class="bg-success">
                            <th rowspan="3">Datum</th>
                            <th>Stunde</th>
                            <th>Klasse</th>
                        </tr>
                        <tr class="bg-success">
                            <th>Lehrer</th>
                            <th>Fächer</th>
                        </tr>
                        <tr class="bg-success">
                            <th colspan="2">Kommentar</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($vertretungen_morgen as $vertretung)
                            <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                <td rowspan="3">{{$vertretung->date->format('d.m.Y')}}</td>
                                <td>{{$vertretung->stunde}}</td>
                                <td>{{$vertretung->klasse->name}}</td>
                            </tr>
                            <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                <td>{{optional($vertretung->lehrer)->kuerzel}}</td>
                                <td>{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                            </tr>
                            <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                <td colspan="2">{{$vertretung->comment}}</td>
                            </tr>
                        @endforeach
                        @foreach($news_heute as $news)
                            <tr @if($loop->iteration%2==0) class="bg-light" @endif>
                                <td colspan="6">
                                    {{$news->news}}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
