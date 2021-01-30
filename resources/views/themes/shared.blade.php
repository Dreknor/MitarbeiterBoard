@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-12">
                        <h5 class="card-title">
                            {{$theme->theme}}
                        </h5>
                    </div>
                </div>
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
                                        <div class="progress">
                                            <div class="progress-bar amount" role="progressbar" style="width: {{100-$theme->priority}}%;" ></div>
                                        </div>
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
                            @if (count($theme->getMedia())>0)
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
                            @endif
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
                                                    @if($task->completed)
                                                        <i class="far fa-check-square text-success " style="font-size: 25px;"></i>
                                                    @endif

                                                    {{$task->date->format('d.m.Y')}} - {{optional($task->taskable)->name}}
                                                    <p>
                                                        {{$task->task}}
                                                    </p>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
            @if(!$share->readonly)
                <div class="card-body mt-2 border-top">
                    <form action="{{url('share/'.$share->uuid.'/protocol')}}" method="post" class="form-horizontal"  enctype="multipart/form-data">
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
                                <input type="text" required name="name" placeholder="Name" value="{{old('name')}}" class="form-control border-input" >
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
                                                @if($protocol->creator_id == auth()->id() and $protocol->created_at->greaterThan(\Carbon\Carbon::now()->subMinutes(config('config.protocols.editableTime'))))
                                                    <div class="pull-right">
                                                        <a href="{{url(request()->segment(1)."/protocols/$protocol->id/edit")}}" class="btn-link btn-danger">
                                                            bearbeiten
                                                        </a>
                                                    </div>
                                                @endif

                                                {{$protocol->created_at->format('d.m.Y H:i')}} - {{$protocol->ersteller->name}}
                                                </p>
                                                {!! $protocol->protocol !!}
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



@push('js')



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

