@extends('layouts.app')
@section('content')
    <div class="container-fluid">
        <a href="{{url(request()->segment(1)."/wochenplan")}}" class="btn btn-primary">zurück</a>
        <div class="card">
            <div class="card-header border-bottom bg-light">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-8">
                            <h6>{{$wochenplan->name}}</h6>
                            <div class="d-inline pull-right">
                                @if (count($wochenplan->rows)>0)
                                    <a href="{{url('wochenplan/'.$wochenplan->id.'/export')}}" class="btn btn-sm btn-primary">export</a>
                                @else
                                    <div class="d-inline pull-right">
                                        <form action="{{url('wochenplan/'.$wochenplan->id.'/remove')}}" method="post">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                                WP löschen
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                            <p class="small">
                                gültig von {{$wochenplan->gueltig_ab->format('d.m.Y')}} bis {{$wochenplan->gueltig_bis->format('d.m.Y')}}
                            </p>
                            <p class="small">
                                @foreach($wochenplan->klassen as $klasse)
                                    {{$klasse->name}}@if(!$loop->last),@endif
                                @endforeach
                            </p>
                        </div>
                        <div class="col-4">
                            <ul class="list-group">
                                @foreach($wochenplan->getMedia()->sortBy('name') as $media)
                                    <li class="list-group-item  list-group-item-action ">
                                        <a href="{{url('/image/'.$media->id)}}" target="_blank" class="mx-auto ">
                                            <i class="fas fa-file-download"></i>
                                            {{$media->name}} (erstellt: {{$media->created_at->format('d.m.Y H:i')}} Uhr)
                                        </a>
                                        <div class="d-inline pull-right">
                                            <form action="{{url('wochenplan/media/'.$media->id.'/remove')}}" method="post">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                        </div>
                    </div>
                </div>
            </div>
            @foreach($wochenplan->rows as $row)
                <div class="card-body border-bottom">
                    <div class="row p-3">
                        <div class="col-4 border-right">
                            <b>
                                {{$row->name}}
                            </b>
                        </div>
                        <div class="col-8">
                            <ul class="list-group">
                                @foreach($row->tasks as $task)
                                    <li class="list-group-item">
                                        <div class="d-inline pull-right">
                                            <div class="d-inline">
                                                <a href="{{url('wptask/'.$task->id.'/edit')}}" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                            </div>
                                            <div class="d-inline  pull-right">
                                                <form action="{{url('wptask/'.$task->id.'/remove')}}" method="post">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>

                                        </div>
                                        <p>
                                            {!! $task->task !!}
                                            @if($wochenplan->hasDuration)
                                                <div class="small m-0 p-0 d-inline">
                                                    Dauer: {{$task->duration}}
                                                </div>
                                            @endif
                                        </p>

                                    </li>
                                @endforeach
                                <li class="list-group-item">
                                    <a href="{{url('wptask/'.$row->id.'/addTask')}}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i>
                                        Aufgabe hinzufügen
                                    </a>
                                    @if (count($row->tasks) < 1)
                                        <div class="d-inline pull-right">
                                            <form action="{{url('wprow/'.$row->id.'/remove')}}" method="post">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                    Abschnitt löschen
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="card-footer bg-light">
                <b>Neuer Abschnitt</b>
                <form action="{{url('wprow/'.$wochenplan->id)}}" class="form-inline" method="post">
                    @csrf
                    <input type="hidden" name="wochenplan_id" value="{{$wochenplan->id}}">
                    <div class="form-check w-75 m-2">
                        <label class="sr-only" for="name">Name</label>
                        <input type="text" class="form-control w-100 p-2 pull-left" id="name" name="name" placeholder="Bezeichnung/Fach">
                    </div>

                    <button type="submit" class="btn btn-primary">Abschnitt erstellen</button>
                </form>
            </div>
            <div class="card-body border-top">
            </div>
            <div class="card-footer border-top bg-light">
                <b>Dateien hinzufügen</b>
                <form action="{{url('wochenplan/'.$wochenplan->id."/addfile")}}" class="form-horizontal" method="post" enctype="multipart/form-data">
                    @csrf
                    <input type="file"  name="files[]" id="customFile" multiple>
                    <button type="submit" class="btn btn-success btn-block">speichern</button>
                </form>
            </div>
        </div>
    </div>


@endsection


@push('css')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/css/fileinput.min.css" media="all" rel="stylesheet" type="text/css" />

@endpush
@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>



    <!-- piexif.min.js is needed for auto orienting image files OR when restoring exif data in resized images and when you
        wish to resize images before upload. This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/piexif.min.js" type="text/javascript"></script>
    <!-- sortable.min.js is only needed if you wish to sort / rearrange files in initial preview.
        This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/sortable.min.js" type="text/javascript"></script>
    <!-- purify.min.js is only needed if you wish to purify HTML content in your preview for
        HTML files. This must be loaded before fileinput.min.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/plugins/purify.min.js" type="text/javascript"></script>
    <!-- popper.min.js below is needed if you use bootstrap 4.x (for popover and tooltips). You can also use the bootstrap js
       3.3.x versions without popper.min.js. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/js/fileinput.min.js"></script>
    <!-- following theme script is needed to use the Font Awesome 5.x theme (`fas`) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-fileinput/5.0.1/themes/fas/theme.min.js"></script>

    <script>
        // initialize with defaults

        console.log('#customFile')
        $("#customFile").fileinput({
            'showUpload':false,
            'previewFileType':'any',
            'maxFileSize': 15000,
            'theme': "fas",
        });
    </script>
@endpush
