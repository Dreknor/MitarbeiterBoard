<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{csrf_token()}}">

    <link rel="shortcut icon" href="{{asset('img/'.config('app.favicon'))}}" type="image/x-icon">

    <title>{{env('APP_NAME')}}</title>

    <!-- CSS Files -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet" />
    <link href="{{asset('css/paper-dashboard.css?v=2.0.0')}}" rel="stylesheet" />
    <link href="{{asset('css/palette-gradient.css')}}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />

    <link href="{{asset('/css/all.css')}}" rel="stylesheet">
    <link href="{{asset('/css/solid.css')}}" rel="stylesheet">
    <link href="{{asset('css/priority.css')}}" rel="stylesheet" />
    <link href="{{asset('css/own.css')}}" rel="stylesheet" />

    {{-- Neue Tailwind-Sidebar --}}
    @vite(['resources/css/sidebar.css', 'resources/js/sidebar.js'])

    {{-- Paed-Diary spezifisches CSS nur auf dieser Route laden --}}
    @if(request()->segment(1) == 'paed-diary')
        @vite(['resources/css/paed-diary.css'])
    @endif

    @stack('css')
</head>

<body id="app-layout" @if(request()->segment(1) == 'paed-diary') class="paed-hide-sidebar" @endif>

{{-- Layout-Wrapper: Sidebar + Hauptinhalt nebeneinander --}}
<div id="app-wrapper">

    {{-- Neue Tailwind-Sidebar --}}
    @include('layouts.partials.sidebar')

    {{-- Mobile Overlay --}}
    <div id="sidebar-overlay"></div>

    {{-- Hauptbereich --}}
    <div id="tw-main">

        {{-- Neue Tailwind-Topbar --}}
        @include('layouts.partials.topbar')

        {{-- Seiteninhalt --}}
        <div class="content">
            @if(session()->has('ownID'))
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="tw-alert tw-alert-info">
                                <i class="fas fa-info-circle"></i>
                                <span>Eingeloggt als: {{auth()->user()->name}}</span>
                                <a href="{{url('logoutAsUser')}}" class="btn btn-sm btn-info ms-2">zum eigenen Account wechseln</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="tw-alert tw-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('Meldung'))
                <div class="container">
                    <div class="row">
                        <div class="col-12">
                            <div class="tw-alert tw-alert-{{session('type', 'info')}}">
                                <span>{{session('Meldung')}}</span>
                                <button class="tw-alert-close" onclick="this.closest('.tw-alert').remove()" aria-label="Schließen">&times;</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @yield('content')
        </div>

        @stack('modals')
    </div>
</div>

<!-- JavaScripts -->
<script src="{{asset('js/core/jquery.min.js')}}"></script>
<script src="{{asset('js/core/jquery-ui.min.js')}}"></script>
<script src="{{asset('js/core/popper.min.js')}}"></script>
<script src="{{asset('js/core/bootstrap.min.js')}}"></script>
<script src="{{asset('js/plugins/perfect-scrollbar.jquery.min.js')}}"></script>

<!--  Notifications Plugin    -->
<script src="{{asset('js/plugins/bootstrap-notify.js')}}"></script>

<!-- Control Center for Now Ui Dashboard -->
<script src="{{asset('js/paper-dashboard.min.js?v=2.0.0')}}"></script>

@auth
    <script src="{{ asset('js/enable-push.js') }}" defer></script>
@endauth
@yield('js')
@stack('js')

{{-- Paed-Diary Floating-Sidebar-Toggle nur für v1 laden (v2 nutzt den Topbar-Button) --}}
@if(request()->segment(1) == 'paed-diary' && request()->segment(2) == 'v1')
    <script src="{{ asset('js/paed-diary.js') }}"></script>
@endif


</body>
</html>
