<!DOCTYPE html>
<html lang="en">
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

    <link href="{{asset('/css/all.css')}}" rel="stylesheet"> <!--load all styles -->
    <link href="{{asset('/css/solid.css')}}" rel="stylesheet"> <!--load all styles -->
    <link href="{{asset('css/priority.css')}}" rel="stylesheet" />
    <link href="{{asset('css/own.css')}}" rel="stylesheet" />

    @stack('css')

</head>

<body id="app-layout">
<div class="sidebar" data-active-color="danger">
    <div class="logo" style="word-wrap: normal;">
        <a href="{{config('app.url')}}" class="simple-text">
            <div class="logo-image-small">
                <img src="{{asset('img/'.config('app.logo'))}}">
            </div>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <ul class="nav">
            @auth
                @cannot('disable menu')
                    <li class="@if(request()->segment(1)=="home" or  request()->segment(1)=="" ) active @endif">
                        <a href="{{url('/home')}}">
                            <i class="fa fa-home"></i>
                            <p>Home</p>
                        </a>
                    </li>
                @can('view wiki')
                        <li class="@if(request()->segment(1)=="wiki") active @endif">
                            <a href="{{url('/wiki')}}">
                                <i class="fa fa-book"></i>
                                <p>Wiki</p>
                            </a>
                        </li>
                @endcan
                @canany(['create roster', 'edit employe', 'has timesheet'])
                    <li>
                        <a data-toggle="collapse" href="#personal">
                            <p>
                                <i class="fas fa-user-friends"></i>
                                Personal <b class="caret"></b>
                            </p>
                        </a>
                        <div class="collapse  @if(request()->segment(1)=="roster" or request()->segment(1)=="timesheets" or request()->segment(1)=="employes") show  active @endif" id="personal">
                            <ul class="nav pl-2">
                                @can('create roster')
                                    <li class="@if(request()->segment(1)=="roster" ) active  @endif">
                                        <a href="{{route('roster.index')}}">
                                            <i class="la la-columns"></i>
                                            <span class="menu-title" data-i18n="">
                                                Dienstpläne
                                            </span>
                                        </a>
                                    </li>
                                @endcan
                                @can('edit employe')
                                    <li class="@if(request()->segment(1)=="employes" or request()->segment(1)=="timesheets") active  @endif">
                                        <li class="@if(Route::currentRouteName() == 'employes.index' or Route::currentRouteName() == 'employes.show') active @endif">
                                            <a class="menu-item" href="{{route('employes.index')}}">
                                                Personal Übersicht
                                            </a>
                                        <li class="@if(request()->segment(1)=="timesheets"  and request()->segment(2) != auth()->id()  and request()->segment(2) != 'import') active  @endif">
                                            <a class="menu-item" href="{{url('timesheets/select/employe')}}">
                                                Arbeitszeitnachweise
                                            </a>
                                        </li>
                                    </li>
                                @endcan
                                @can('has timesheet')
                                        <li class="@if(request()->segment(1)=="timesheets" and request()->segment(2) == auth()->id()) active  @endif">
                                            <a class="menu-item" href="{{url('timesheets/'.auth()->id())}}">
                                                eigene Arbeitszeitnachweise
                                            </a>
                                        </li>
                                @endcan
                                @canany(['has holidays', 'approve holidays'])
                                        <li class="@if(request()->segment(1)=="holidays") active  @endif">
                                            <a class="menu-item" href="{{route('holidays.index')}}">
                                                Urlaub
                                            </a>
                                        </li>
                                @endcan
                                </li>
                            </ul>
                        </div>
                    </li>
                @endcanany
                    @can('view roomBooking')
                        <li class="@if(request()->segment(1)=="rooms" ) active  @endif">
                            <a href="{{url('rooms/rooms')}}">
                                <p>
                                    <i class="fa fa-calendar-alt"></i>
                                    Raumplan

                                </p>

                            </a>
                        </li>

                    @endif
                    @can('view procedures')
                        <li>
                            <a href="{{url('/procedure')}}">
                                <i class="fas fa-project-diagram"></i>
                                <p>Prozesse</p>
                            </a>
                        </li>
                    @endcan
                    @can('see terminlisten')
                        <li>
                            <a href="{{url('/listen')}}">
                                <i class="fas fa-calendar"></i>
                                <p>Listen</p>
                            </a>
                        </li>
                    @endcan
                    @can('edit inventar')
                        <li class="@if(request()->segment(1)=="inventory" ) active  @endif">
                            <a data-toggle="collapse" href="#inventory">
                                <p>
                                    <i class="fas fa-boxes"></i>
                                     Inventar
                                    <b class="caret"></b>
                                </p>

                            </a>
                            <div class="collapse @if(request()->segment(1)=="inventory" ) show  @endif" id="inventory">
                                <ul class="nav pl-2">
                                    <li class="@if(request()->segment(2)=="locations" and request()->segment(1)=="inventory" ) active @endif">
                                        <a href="{{url('inventory/locations')}}">
                                            <i class="fas fa-map-marker"></i>
                                            <p>Standort</p>
                                        </a>
                                    </li>
                                    <li class="@if(request()->segment(2)=="categories" and request()->segment(1)=="inventory" ) active @endif">
                                        <a href="{{url('inventory/categories')}}">
                                            <i class="far fa-folder-open"></i>
                                            <p>Kategorien</p>
                                        </a>
                                    </li>
                                    <li class="@if(request()->segment(2)=="lieferanten" and request()->segment(1)=="inventory" ) active @endif">
                                        <a href="{{url('inventory/lieferanten')}}">
                                            <i class="fas fa-shipping-fast"></i>
                                            <p>Lieferanten</p>
                                        </a>
                                    </li>
                                    <li class="@if(request()->segment(2)=="items" and request()->segment(1)=="inventory" ) active @endif">
                                        <a href="{{url('inventory/items')}}">
                                            <i class="fas fa-dice-d6"></i>
                                            <p>Inventar</p>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    @endcan
                    <li>
                        <a data-toggle="collapse" href="#Beratungen" >
                            <p>
                                <i class="far fa-comments" ></i>
                                Beratungen <b class="caret"></b>
                            </p>
                        </a>
                        <div class="collapse  @if(request()->segment(2)=="themes" or request()->segment(2)=="memory"  or request()->segment(2)=="archive"  or request()->segment(2)=="search"  or request()->segment(2)=="export") show  active @endif" id="Beratungen">
                            <ul class="nav pl-2">
                                <li class="@if(request()->segment(1)=="search") active @endif">
                                    <a href="{{url('/search')}}">
                                        <i class="fa fa-search"></i>
                                        <p>Suche</p>
                                    </a>
                                </li>
                                @foreach(auth()->user()->groups() AS $group)
                                    <li>

                                        <a data-toggle="collapse" href="#{{$group->name}}">
                                            <p>
                                                {{$group->name}} <b class="caret"></b>
                                            </p>
                                        </a>
                                        <div class="collapse @if(request()->segment(1)=="$group->name" ) show  @endif" id="{{$group->name}}">
                                            <ul class="nav pl-2">
                                                <li class="@if(request()->segment(2)=="themes" and request()->segment(3)!="recurring" and request()->segment(1)=="$group->name" ) active @endif">
                                                    <a href="{{url($group->name.'/themes#'.\Carbon\Carbon::now()->format('Ymd'))}}">
                                                        <i class="far fa-comments"></i>
                                                        <p>Themen</p>
                                                    </a>
                                                </li>
                                                <li class="@if(request()->segment(2)=="archive" and request()->segment(1)=="$group->name"  ) active @endif">
                                                    <a href="{{url($group->name.'/archive')}}">
                                                        <i class="fas fa-archive"></i>
                                                        <p>Archiv</p>
                                                    </a>
                                                </li>
                                                <li class="@if(request()->segment(2)=="export" and request()->segment(1)=="$group->name"  ) active @endif">
                                                    <a href="{{url($group->name.'/export')}}">
                                                        <i class="fas fa-file-alt"></i>
                                                        <p>Protokoll</p>
                                                    </a>
                                                </li>
                                                <li class="@if(request()->segment(2)=="search"  and request()->segment(1)=="$group->name") active @endif">
                                                    <a href="{{url($group->name.'/search')}}">
                                                        <i class="fas fa-search"></i>
                                                        <p>Suche</p>
                                                    </a>
                                                </li>
                                                <li class="@if(request()->segment(2)=="memory"  and request()->segment(1)=="$group->name") active @endif">
                                                    <a href="{{url($group->name.'/memory')}}">
                                                        <i class="fas fa-save"></i>
                                                        <p>Themenspeicher</p>
                                                    </a>
                                                </li>
                                                @can('create Wochenplan')
                                                    @if($group->hasWochenplan == 1)
                                                        <li class="@if(request()->segment(2)=="wochenplan"  and request()->segment(1)=="$group->name") active @endif">
                                                            <a href="{{url($group->name.'/wochenplan')}}">
                                                                <i class="fas fa-tasks"></i>
                                                                <p>Wochenplan</p>
                                                            </a>
                                                        </li>
                                                    @endif
                                                @endcan
                                                @can('manage recurring themes')
                                                    <li class="@if(request()->segment(3)=="recurring" and request()->segment(2)=="themes" and request()->segment(1)=="$group->name" ) active @endif">
                                                        <a href="{{url($group->name.'/themes/recurring')}}">
                                                            <i class="fas fa-redo"></i>
                                                            <p>wiederk. Themen</p>
                                                        </a>
                                                    </li>
                                                @endcan
                                            </ul>
                                        </div>
                                    </li>

                                @endforeach
                            </ul>
                        </div>
                    </li>

                    @can('edit vertretungen')
                        <li>
                            <a data-toggle="collapse" href="#Vertretung">
                                <p>
                                    <i class="fas fa-columns"></i>
                                    Vertretungsplan <b class="caret"></b>
                                </p>
                            </a>
                            <div class="collapse  @if(request()->segment(1)=="vertretungen" or request()->segment(1)=="dailyNews" or request()->segment(1)=="weeks") show  active @endif" id="Vertretung">
                                <ul class="nav pl-2">
                                    <li class="@if(request()->segment(1)=="vertretungen") active @endif">
                                        <a href="{{url('/vertretungen')}}">
                                            <i class="fas fa-sync"></i>
                                            <p>Vertretungen</p>
                                        </a>
                                    </li>
                                    <li class="@if(request()->segment(1)=="dailyNews") active @endif">
                                        <a href="{{url('/dailyNews')}}">
                                            <i class="fas fa-newspaper"></i>
                                            <p>News</p>
                                        </a>
                                    </li>
                                    <li class="@if(request()->segment(1)=="weeks") active @endif">
                                        <a href="{{url('/weeks')}}">
                                            <i class="fas fa-calendar"></i>
                                            <p>Wochen</p>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                    @endcan
                <!-- Verwaltung -->
            <hr>
                @can('manage sick_notes')
                    <li class="@if(request()->segment(1)=="groups" ) active @endif">
                        <a href="{{url('sick_notes')}}">
                            <i class="fas fa-notes-medical"></i>
                            <p>Krankschreibungen</p>
                        </a>
                    </li>
                @endcan
                @can('view old absences')
                    <li class="@if(request()->segment(1)=="groups" ) active @endif">
                        <a href="{{url('absences')}}">
                            <i class="fas fa-user-clock"></i>
                            <p>Abwesenheiten</p>
                        </a>
                    </li>
                @endcan
                <li class="@if(request()->segment(1)=="groups" ) active @endif">
                    <a href="{{url('/groups')}}">
                        <i class="fas fa-users"></i>
                        <p>Gruppen</p>
                    </a>
                </li>


                    @can('edit klassen')
                        <li class="@if(request()->segment(1)=="klassen") active @endif">
                            <a href="{{url('/klassen')}}">
                                <i class="fas fa-users"></i>
                                <p>Klassen</p>
                            </a>
                        </li>
                    @endcan
                    @can('edit permissions')
                        <li class="@if(request()->segment(1)=="roles" and request()->segment(2)!="user"  ) active @endif">
                            <a href="{{url('/roles')}}">
                                <i class="fas fa-lock"></i>
                                <p>Rechte</p>
                            </a>
                        </li>
                    @endcan
                    @can('edit users')
                        <li class="@if(request()->segment(1)=="users") active @endif">
                            <a href="{{url('/users')}}">
                                <i class="fas fa-user"></i>
                                <p>Benutzer</p>
                            </a>
                        </li>
                    @endcan
                    @can('create types')
                        <li class="@if(request()->segment(1)=="types") active @endif">
                            <a href="{{url('/types')}}">
                                <i class="fas fa-comments"></i>
                                <p>Thementypen</p>
                            </a>
                        </li>
                    @endcan
                    @can('edit settings')
                        <li class="@if(request()->segment(1)=="types") active @endif">
                            <a href="{{url('/settings')}}">
                                <i class="fas fa-edit"></i>
                                <p>Einstellungen</p>
                            </a>
                        </li>
                    @endcan
                @endcan
            @endauth

        </ul>

    </div>

</div>
<div class="main-panel">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent">
        <div class="container-fluid">
            <div class="navbar-wrapper">
                <div class="navbar-toggle">
                    <button type="button" class="navbar-toggler">
                        <span class="navbar-toggler-bar bar1"></span>
                        <span class="navbar-toggler-bar bar2"></span>
                        <span class="navbar-toggler-bar bar3"></span>
                    </button>
                </div>
                <a class="navbar-brand" href="{{url('/')}}">{{env('APP_NAME')}}</a>
            </div>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navigation" aria-controls="navigation-index" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-bar navbar-kebab"></span>
                <span class="navbar-toggler-bar navbar-kebab"></span>
                <span class="navbar-toggler-bar navbar-kebab"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navigation">


                <ul class="nav-item navbar-nav nav-bar-right w-auto">

                            @if (Auth::guest())
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ url('/login') }}">Login</a>
                                </li>
                            @else
                                <li class="dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <i class="far fa-user"></i>
                                        <p>{{auth()->user()->name}}</p>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                                                Logout
                                            </a>
                                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                                @csrf
                                            </form>
                                        </li>

                                    </ul>
                                </li>
                            @endif

                    <!-- Authentication Links -->


                </ul>
            </div>
        </div>
    </nav>
    <!-- End Navbar -->




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
