@extends('layouts.app')

@section('content')
    <div class="floating-timer" id="timer">
       <span id="duration"></span>
    </div>
    <div class="container-fluid" id="top">
        <div class="floating-button-menu menu-off">
            <div class="floating-button-menu-links">
                @if (($theme->creator_id == auth()->id() or auth()->user()->can('complete theme') or (!$theme->group->proteced and auth()->user()->groups()->contains($theme->group))) and !$theme->completed)
                    <a href="{{url(request()->segment(1)."/themes/$theme->id/close")}}" class="bg-danger text-light">
                        <i class="fas fa-lock"></i>
                        <span>
                            Abschließen
                        </span>
                    </a>
                @endif
                @can('share theme')
                    @if($theme->share == null)
                        <a  href="#top" id="shareBtn" class="">
                            <i class="fas fa-share-alt"></i>
                            <span>
                                    freigeben
                                </span>
                        </a>
                    @else
                        <form method="post" action="{{url('share/'.$theme->id)}}" class="border p-1 bg-warning">
                            @csrf
                            @method('delete')
                            <input type="hidden" name="theme" value="{{base64_encode($theme->id)}}">
                            <button type="submit" class="btn-link" href="{{url('/share/')}}">
                                <i class="fas fa-share-alt"></i>
                                Freigabe entfernen
                            </button>
                        </form>
                    @endif
                @endcan
                <a href="{{url(request()->segment(1)."/memory/$theme->id")}}">
                    <i class="fas fa-memory"></i>
                    <span>
                        in Speicher
                    </span>
                </a>
                <a href="{{url(request()->segment(1)."/themes/$theme->id/edit")}}">
                    <i class="fas fa-pen"></i>
                    <span>
                        Bearbeiten
                    </span>
                </a>
                @if($subscription == null)
                    <a href="{{url("subscription/theme/$theme->id/")}}" >
                        <i class="far fa-bell"></i>
                        <span>
                            Abonnieren
                        </span>
                    </a>
                @else
                    <a href="{{url("subscription/theme/$theme->id/remove")}}">
                        <i class="fas fa-bell"></i>
                        <span>
                            Abo beenden
                        </span>
                    </a>
                @endif
                    @if (($theme->creator_id == auth()->id() or auth()->user()->can('create themes')) and !$theme->completed)
                        <div class="dropdown">
                                <a class="dropdown-toggle" type="button" id="dropdownMoveButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Verschieben zum
                                </a>
                                <div class="dropdown-menu text-center" aria-labelledby="dropdownMoveButton">
                                    <span class="dropdown-item bg-gradient-radial-blue-grey text-white">Verschieben zum</span>
                                    @for($x=0; $x<8; $x++)
                                        <a class="dropdown-item" href="{{url(request()->segment(1).'/move/theme/'.$theme->id.'/'.\Carbon\Carbon::now()->next($group->weekday_name())->addWeeks($x)->format('Y-m-d').'/true')}}">{{\Carbon\Carbon::now()->next($group->weekday_name())->addWeeks($x)->format('d.m.Y')}}</a>
                                    @endfor
                                </div>
                        </div>
                    @endif
                    @if (!$theme->completed)
                        <a href="{{url(request()->segment(1).'/protocols/'.$theme->id)}}">
                            ausführliches Protokoll anlegen
                        </a>
                        <a type="button" data-toggle="modal" data-target="#taskModal">
                            Aufgabe erstellen
                        </a>
                        <a href="{{url($theme->group->name.'/themes/'.$theme->id.'/survey/create')}}">
                                Umfrage erstellen
                        </a>
                        @if($theme->group->hasAllocations and auth()->user()->groups_rel->contains($theme->group))
                                <div class="dropdown">
                                    <a class="dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        @if($theme->zugewiesen_an != null)
                                            zugewiesen: {{$theme->zugewiesen_an->name}}
                                        @else
                                            Zuweisen an
                                        @endif
                                    </a>
                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                        <span class="dropdown-item bg-gradient-directional-amber text-white">Zuweisen an</span>
                                        @foreach($theme->group->users as $user)
                                            @if($theme->zugewiesen_an == null or $theme->zugewiesen_an->id != $user->id )
                                                <a class="dropdown-item" href="{{url('theme/'.$theme->id.'/assign/'.$user->id)}}">{{$user->name}}</a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                        @endif
                        @can('move themes')
                                <div class="dropdown">
                                    <a class="dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        In andere Gruppe verschieben
                                    </a>
                                    <div class="dropdown-menu  text-center" aria-labelledby="dropdownMenuButton">
                                        <span class="dropdown-item bg-gradient-radial-blue-grey text-white">Verschieben nach</span>
                                        @foreach(auth()->user()->groups() as $group)
                                            @if($theme->group_id !=  $group->id )
                                                <a class="dropdown-item" href="{{url('theme/'.$theme->id.'/change/group/'.$group->id)}}">{{$group->name}}</a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                        @endcan
                        @if($theme->creator_id == auth()->user()->id and $theme->protocols->count() == 0 and $theme->priority == null and $theme->date->startOfDay()->greaterThan(\Carbon\Carbon::now()->startOfDay()))
                            <form action="{{url(request()->segment(1).'/themes/'.$theme->id)}}" method="post">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">LÖSCHEN</button>
                            </form>
                        @endif
                    @endif
            </div>
            <div class="floating-button-menu-label"><i class="fa fa-bars"></i></div>
        </div>
        <div class="floating-button-menu-close"></div>

            <p>
                <a href="{{url(request()->segment(1).'/themes#'.$theme->id)}}" class="btn btn-primary btn-link">zurück</a>

            </p>

            <div class="card">
                <div class="card-header">
                <div class="row">
                    <div class="col-lg-auto col-md-8 col-sm-12">
                        @if($theme->zugewiesen_an != null) <div class="badge bg-gradient-directional-amber p-2">{{$theme->zugewiesen_an->name}}</div> @endif
                            <h5 class="card-title">
                                {{$theme->theme}}
                            </h5>
                    </div>
                </div>
            </div>
            </div>
        <div class="card">
            <div class="card-body border-top collapse hide" id="shareForm">
                <form method="post" action="{{url('share/'.$theme->id)}}" class="form-horizontal">
                    @csrf
                    <input type="hidden" name="theme" value="{{base64_encode($theme->id)}}">
                    <div class="form-row">
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="activ_until">
                                    gültig bis
                                </label>
                                <input type="date" name="active_until" class="form-control" id="activ_until" required value="{{\Carbon\Carbon::now()->addWeek()->format('Y-m-d')}}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="readonly">
                                    Dürfen Protokolle angelegt werden?
                                </label>
                                <select class="custom-select" name="readonly">
                                    <option value="1">nur lesbar</option>
                                    <option value="0">auch bearbeitbar</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-warning btn-block" id="btn">freigeben</button>
                            </div>
                        </div>

                    </div>
                </form>
            </div>
            <div class="card-body border-top">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-12 col-md-12 col-lg-8">
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Priorität
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    @if ($theme->completed or $theme->priorities->where('creator_id', auth()->id())->first())
                                        <div class="d-inline pull-right">
                                            <a href="{{route('priorities.delete',[$theme->id])}}">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar amount" role="progressbar" style="width: {{100-$theme->priority}}%;" ></div>
                                        </div>

                                    @else
                                        <input type="range" class="custom-range" id="theme_{{$theme->id}}" min="1" max="100" value="0" data-theme = "{{$theme->id}}">
                                    @endif
                                </div>
                            </div>
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Von
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    <img src="{{$theme->ersteller->photo()}}" class="avatar-xs">  {{$theme->ersteller->name}}
                                </div>
                            </div>
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Typ
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    {{$theme->type->type}}
                                </div>
                            </div>
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Ziel
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    {{ $theme->goal}}
                                </div>
                            </div>
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Erstellt
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    {{$theme->created_at->format('d.m.Y H:i')}} Uhr
                                </div>
                            </div>
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Dauer
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    {{$theme->duration}} Minuten
                                </div>
                            </div>
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Informationen
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    {!! $theme->information !!}
                                </div>
                            </div>
                            @if($theme->share)
                                <div class="row p-2">
                                    <div class="col-sm-12 col-md-12 col-lg-3">
                                        <b>
                                            Freigabe
                                        </b>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-9">
                                        <a href="{{url('share/'.$theme->share->uuid)}}">
                                            {{url('share/'.$theme->share->uuid)}}
                                        </a>
                                    </div>
                                </div>
                            @endif
                            <div class="row p-2">
                                <div class="col-sm-12 col-md-12 col-lg-3">
                                    <b>
                                        Dateien
                                    </b>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-9">
                                    <ul class="list-group">
                                        @foreach($theme->getMedia()->sortBy('name') as $media)
                                            <li class="list-group-item  list-group-item-action ">
                                                <a href="{{url('/image/'.$media->id)}}" target="_blank" class="mx-auto ">
                                                    <i class="fas fa-file-download"></i>
                                                    {{$media->name}} (erstellt: {{$media->created_at->format('d.m.Y H:i')}} Uhr)
                                                </a>

                                            </li>
                                        @endforeach

                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-4 border-left p-sm-2 p-md-2">
                            <div class="container-fluid">
                                <div class="row">
                                    <div class="col-12">
                                        <b>
                                            Aufgaben:
                                        </b>
                                        <ul class="list-group">
                                            @foreach($theme->tasks->sortByDate('date', 'desc') as $task)
                                                @if(!is_null($task->taskable))
                                                    <li class="list-group-item">
                                                        @if($task->completed or (get_class($task->taskable) == 'App\Models\Group' and $task->taskUsers->count() == "0"))
                                                            <i class="far fa-check-square text-success " style="font-size: 25px;"></i>
                                                        @endif

                                                        {{$task->date->format('d.m.Y')}} - {{optional($task->taskable)->name}}
                                                        <p>
                                                            {{$task->task}}
                                                            @if($task->taskUsers->count() >0)
                                                                <small>
                                                                    (noch offen: {{$task->taskUsers->count()}} )
                                                                </small>
                                                            @endif
                                                        </p>

                                                    </li>
                                                @endif

                                            @endforeach
                                        </ul>
                                    </div>

                                </div>
                                @can('view priorities')
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <b>
                                                Prioritäten:
                                            </b>
                                            <br>
                                            <ul class="list-group">
                                                @foreach($theme->priorities as $priority)
                                                    <li class="list-group-item">
                                                        <label class="w-100">
                                                            {{$priority->creator->name}}
                                                            <div class="progress">
                                                                <div class="progress-bar amount" role="progressbar" style="width: {{100-$priority->priority}}%;" ></div>
                                                            </div>
                                                        </label>

                                                    </li>

                                                @endforeach
                                            </ul>
                                        </div>

                                    </div>
                                @endcan
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <div class="card-body border-top">
                <div class="row p-2">

                </div>
            </div>
            @if (!$theme->completed)
                <div class="card-body mt-2 border-top">
                <form action="{{url(request()->segment(1).'/protocols/'.$theme->id)}}" method="post" class="form-horizontal"  enctype="multipart/form-data">
                    @csrf
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <div class="container-fluid">
                                <div class="row">
                                    <label for="protocol">Schnelles Protokoll</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-12">
                            <textarea name="protocol"  class="form-control border-input" >
                                {{old('protocol')}}
                            </textarea>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-bg-gradient-x-blue-green btn-block">speichern</button>
                        </div>

                    </div>
                </form>
            </div>
            @endif
            @foreach($theme->surveys as $survey)
                @include('themes.element.survey')
            @endforeach
            <div class="card-body">
                <div class="row mt-2 border-top">
                    <div class="container-fluid">
                        <div class="row mt-2">
                            <div class="col-12">
                                <b>
                                    Protokoll
                                </b>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 col-md-12 col-lg-12">
                                @if ($theme->protocols->count() == 0 )
                                    <p>
                                        Kein Protokoll vorhanden
                                    </p>
                                @else
                                    <ul class="list-group">
                                        @foreach($theme->protocols->sortDesc() as $protocol)
                                            <li class="list-group-item">
                                                <p>
                                                    @if(($protocol->creator_id == auth()->user()->id and $protocol->created_at->greaterThan(\Carbon\Carbon::now()->subMinutes(config('config.protocols.editableTime')))) or $theme->change_protokoll == true)
                                                        <div class="pull-right">
                                                            <a href="{{url(request()->segment(1)."/protocols/$protocol->id/edit")}}" class="btn-link btn-danger">
                                                                bearbeiten
                                                            </a>
                                                        </div>
                                                    @endif

                                                    {{$protocol->created_at->format('d.m.Y H:i')}} - @if($protocol->ersteller->getMedia('profile')->count() != 0)<img src="{{$protocol->ersteller->photo()}}" class="avatar-xs" style="max-height: 30px; max-width: 30px;">@endif {{$protocol->ersteller->name}}
                                                </p>

                                                {!! $protocol->protocol !!}

                                                <ul class="list-group">
                                                    @foreach($protocol->getMedia()->sortBy('name') as $media)
                                                        <li class="list-group-item  list-group-item-action ">
                                                            <a href="{{url('/image/'.$media->id)}}" target="_blank" class="mx-auto ">
                                                                <i class="fas fa-file-download"></i>
                                                                {{$media->name}} (erstellt: {{$media->created_at->format('d.m.Y H:i')}} Uhr)
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('modals')
    <div class="modal fade" id="taskModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg " role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aufgabe hinzufügen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url(request()->segment(1).'/'.$theme->id.'/tasks')}}" method="post" class="form-horizontal" id="taskForm">
                        @csrf
                        <div class="form-row p-2">
                            <label for="date">zu erledigen bis...</label>
                            <input type="date" name="date" min="{{\Carbon\Carbon::now()->addDay()->format('Y-m-d')}}" value="{{old('date')}}" class="form-control" required autofocus>
                        </div>
                        <div class="form-row p-2">
                            <label for="task">Aufgabe</label>
                            <input type="text" name="task" min="{{\Carbon\Carbon::now()->addDay()->format('Y-m-d')}}" value="{{old('task')}}" class="form-control" required>
                        </div>
                        <div class="form-row p-2">
                            <label for="taskable">Aufgabe für ...</label>
                            <select class="custom-select" name="taskable">
                                <option value="{{request()->segment(1)}}">Gruppe {{request()->segment(1)}}</option>
                                @foreach($theme->group->users as $user)
                                    <option value="{{$user->id}}">{{$user->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit"  form="taskForm" class="btn btn-primary">Speichern</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endpush


@push('js')

    <script>
        function makeTimer() {

            let outline =''
            var endTime = new Date("{{\Carbon\Carbon::now()->addMinutes($theme->duration)->format('Y-m-d H:i:s')}}");
            endTime = (Date.parse(endTime) / 1000);

            var now = new Date();
            now = (Date.parse(now) / 1000);

            var timeLeft = endTime - now;
            var out = "";

            if (timeLeft > 0){
                var hours = Math.floor((timeLeft) / 3600);
                var minutes = Math.floor((timeLeft - (hours * 3600 )) / 60);
                var seconds = Math.floor((timeLeft  - (hours * 3600) - (minutes * 60)));


            } else {
                timeLeft = now - endTime;
                var hours = Math.floor((timeLeft) / 3600);
                var minutes = Math.floor((timeLeft - (hours * 3600 )) / 60);
                var seconds = Math.floor((timeLeft  - (hours * 3600) - (minutes * 60)));

                out = "-";

                if(!$("#timer").hasClass("btn-outline-danger")){
                    $("#timer").addClass("btn-outline-danger");
                    $("#timer").addClass("floating-timer-sub");
                }
            }


            if (hours < "10") { hours = "0" + hours; }
            if (minutes < "10") { minutes = "0" + minutes; }
            if (seconds < "10") { seconds = "0" + seconds; }
            $('#duration').html(out + hours + ":" + minutes + ":" + seconds);
        }

        setInterval(function() { makeTimer(); }, 1000);
    </script>

    <script>
        $('#shareBtn').on("click", function() {
            $('#shareForm').toggle();
        });


    </script>

    <script>
        $('input[type=range]').on("change", function() {
            let theme = $(this).data('theme');
            let url = "{{url(request()->segment(1).'/themes/'.$theme->id)}}";
            $.ajax({
                type: "POST",
                url: '{{url('priorities')}}',
                data: {
                    "priority": $(this).val(),
                    'theme': theme,
                    "_token": "{{ csrf_token() }}",
                },
                success: function(responseText){
                    window.location.replace(url);
                }
            });
        });
    </script>

    <script src="{{asset('js/plugins/tinymce/jquery.tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/langs/de.js')}}"></script>
    <script>
        tinymce.init({
            selector: 'textarea',
            lang:'de',
            height: 500,
            menubar: true,
            autosave_ask_before_unload: true,
            autosave_interval: '40s',
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
                'contextmenu autosave',
            ],
            toolbar: 'undo redo  | bold italic backcolor forecolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link | restoredraft',
            contextmenu: " link paste inserttable | cell row column deletetable",
            table_default_attributes: {
                border: '1'
            }
        });
    </script>



    <script>
        $( ".menu-off" ).click(function() {
            $( this ).removeClass( "menu-off" );
            $( this ).addClass( "menu-on" );
            $('.floating-button-menu-close').addClass('menu-on');
        });
        $('.floating-button-menu-close').click(function(){
            $( this ).addClass( "menu-off" );
            $( this ).removeClass( "menu-on" );
            $('.floating-button-menu').toggleClass('menu-on');
        });



    </script>


@endpush

@push('css')
    <link href="{{asset('css/floating_menu.css')}}" rel="stylesheet">
    <link href="{{asset('css/timer.css')}}" media="all" rel="stylesheet" type="text/css" />

@endpush
