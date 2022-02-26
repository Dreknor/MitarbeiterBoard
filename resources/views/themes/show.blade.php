@extends('layouts.app')

@section('content')
    <div class="container-fluid">
            <p>
                <a href="{{url(request()->segment(1).'/themes#'.$theme->id)}}" class="btn btn-primary btn-link">zurück</a>

            </p>


        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-lg-6 col-md-12 col-sm-12">
                        <h5 class="card-title">
                            {{$theme->theme}}
                        </h5>
                    </div>
                    <div class="col-lg-6 col-md-12 col-sm-12">
                        <div class="pull-right">
                            <div class="row">
                                    @if (($theme->creator_id == auth()->id() or auth()->user()->can('create themes')) and !$theme->completed)
                                        <div class="col-auto">
                                            <a href="{{url(request()->segment(1)."/themes/$theme->id/edit")}}" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-pen"></i>
                                                <div class="d-none d-md-none d-lg-inline-block">
                                                    Bearbeiten
                                                </div>
                                            </a>
                                        </div>
                                        <div class="col-auto">
                                            <a href="{{url(request()->segment(1)."/memory/$theme->id")}}" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-memory"></i>
                                                <div class="d-none d-md-none d-lg-inline-block">
                                                    in Speicher
                                                </div>
                                            </a>
                                        </div>
                                    @endif
                                    @if (($theme->creator_id == auth()->id() or auth()->user()->can('complete theme')) and !$theme->completed)
                                        <div class="col-auto">
                                            <a href="{{url(request()->segment(1)."/themes/$theme->id/close")}}" class="btn btn-sm btn-outline-danger pull-right">
                                                <i class="fas fa-lock"></i>
                                                <div class="d-none d-md-none d-lg-inline-block">
                                                    Abschließen
                                                </div>
                                            </a>
                                        </div>
                                    @endif
                                        @can('share theme')
                                            @if($theme->share == null)
                                                <div class="col-auto">
                                                    <a class="btn btn-sm btn-outline-warning pull-right" href="#" id="shareBtn">
                                                        <i class="fas fa-share-alt"></i>
                                                        <div class="d-none d-md-none d-lg-inline-block">
                                                            freigeben
                                                        </div>
                                                    </a>
                                                </div>
                                            @else
                                                <div class="col-auto">
                                                    <form method="post" action="{{url('share/'.$theme->id)}}" >
                                                        @csrf
                                                        @method('delete')
                                                        <input type="hidden" name="theme" value="{{base64_encode($theme->id)}}">
                                                        <button type="submit" class="btn btn-sm btn-warning p-2 pull-right" href="{{url('/share/')}}">
                                                            <i class="fas fa-share-alt"></i>
                                                            Freigabe entfernen
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        @endcan

                                        <div class="col-auto">
                                            @if($subscription == null)
                                                <a href="{{url("subscription/theme/$theme->id/")}}" class="btn btn-sm btn-outline-info">
                                                    <i class="far fa-bell"></i>
                                                    <div class="d-none d-md-none d-lg-inline-block">
                                                        Abonieren
                                                    </div>
                                                </a>
                                            @else
                                                <a href="{{url("subscription/theme/$theme->id/remove")}}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-bell"></i>
                                                    <div class="d-none d-md-none d-lg-inline-block">
                                                        Abo beenden
                                                    </div>
                                                </a>
                                            @endif
                                        </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body border-top collapse hide" id="shareForm">
                <form method="post" action="{{url('share/'.$theme->id)}}" class="form-horizontal">
                    @csrf
                    <input type="hidden" name="theme" value="{{base64_encode($theme->id)}}">
                    <div class="form-row">
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="activ_until">
                                    gueltig bis (ohne Angabe unbegrenzt)
                                </label>
                                <input type="date" name="active_until" class="form-control" id="activ_until">
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
                                    {{$theme->ersteller->name}}
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
                                                <li class="list-group-item">
                                                    @if($task->completed or $task->taskUsers->count() == 0)
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
                    @if (!$theme->completed)
                        <div class="col">
                            <a href="{{url(request()->segment(1).'/protocols/'.$theme->id)}}" class="btn btn-primary btn-block">ausführliches Protokoll anlegen</a>
                        </div>
                        <div class="col">
                            <button type="button" class="btn btn-secondary btn-block" data-toggle="modal" data-target="#taskModal">
                                Aufgabe erstellen
                            </button>
                        </div>
                        @if($theme->creator_id == auth()->user()->id and $theme->protocols->count() == 0 and $theme->priority == null and $theme->date->startOfDay()->greaterThan(\Carbon\Carbon::now()->startOfDay()))
                            <form action="{{url(request()->segment(1).'/themes/'.$theme->id)}}" method="post">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">LÖSCHEN</button>
                            </form>
                        @endif
                    @endif
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
                            <button type="submit" class="btn btn-success btn-block">speichern</button>
                        </div>

                    </div>
                </form>
            </div>
            @endif
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
                                                    @if($protocol->creator_id == auth()->user()->id and $protocol->created_at->greaterThan(\Carbon\Carbon::now()->subMinutes(config('config.protocols.editableTime'))))
                                                        <div class="pull-right">
                                                            <a href="{{url(request()->segment(1)."/protocols/$protocol->id/edit")}}" class="btn-link btn-danger">
                                                                bearbeiten
                                                            </a>
                                                        </div>
                                                    @endif

                                                    {{$protocol->created_at->format('d.m.Y H:i')}} - {{$protocol->ersteller->name}}
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
            height: 200,
            menubar: false,
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
                'contextmenu',
            ],
            toolbar: 'undo redo  | bold italic backcolor forecolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link ',
            contextmenu: " link paste inserttable | cell row column deletetable",
        });
    </script>
@endpush

