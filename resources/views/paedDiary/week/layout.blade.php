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

    <style>
        html, body {
            height: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        body {
            width: 100vw;
            height: 100vh;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .bg-secondary {
            min-height: 100vh;
            height: 100%;
        }

        #week-table {
            height: 100vh;
            width: 100%;
            font-size: 1.0vw; /* Skaliert Schriftgröße mit Bildschirmbreite */
            table-layout: fixed;
        }
        #week-table th, #week-table td {
            vertical-align: middle;
            text-align: center;

        }
    </style>
    @stack('css')

</head>
<body>
<div class="bg-secondary" style='width: 100%; height: 100%; background-color: #f4f3ef; background-image: url("{!! asset('img/'.settings('show_background')) !!}")'>
    @yield('content')
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
