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
    <link href="{{asset('css/priority.css')}}" rel="stylesheet" />
    @stack('css')

</head>

<body id="app-layout">
<div class="main-panel">



    <div class="content">
        @if(session()->has('ownID'))
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <p>Eingeloggt als: {{auth()->user()->name}}</p>
                            <p>
                                <a href="{{url('logoutAsUser')}}" class="btn btn-info">zum eigenen Account wechseln</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('Meldung'))
            <div class="container">
                <div class="row">
                    <div class="col-12" >
                        <div class="alert alert-{{session('type')}} alert-dismissible" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            {{session('Meldung')}}

                        </div>
                    </div>
                </div>
            </div>
        @endif
        @yield('content')



    </div>

    @stack('modals')
</div>
<!-- JavaScripts -->

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

    @auth
        <script src="{{ asset('js/enable-push.js') }}" defer></script>
    @endauth
    @yield('js')
    @stack('js')

</body>
</html>
