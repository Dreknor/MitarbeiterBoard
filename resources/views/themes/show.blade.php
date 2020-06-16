@extends('layouts.app')

@section('content')
    <div class="container-fluid">
            <p>
                <a href="{{url(request()->segment(1).'/themes')}}" class="btn btn-primary btn-link">zurück</a>
                @if (($theme->creator_id == auth()->id() or auth()->user()->can('create themes')) and !$theme->completed)
                    <a href="{{url(request()->segment(1)."/themes/$theme->id/edit")}}" class="btn btn-warning btn-link pull-right">bearbeiten</a>
                @endif
            </p>


        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    {{$theme->theme}}
                </h5>
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
                            <b>
                                Aufgaben:
                            </b>
                            <ul class="list-group">
                                @foreach($theme->tasks->sortByDate('date', 'desc') as $task)
                                    <li class="list-group-item">
                                        @if($task->completed)
                                                <i class="far fa-check-square text-success " style="font-size: 25px;"></i>
                                        @endif

                                        {{$task->date->format('d.m.Y')}} - {{$task->taskable->name}}
                                        <p>
                                            {{$task->task}}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                </div>


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
                                        @foreach($theme->protocols as $protocol)
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
                <div class="row p-2">
                    @if (!$theme->completed)
                        <div class="col-6">
                               <a href="{{url(request()->segment(1).'/protocols/'.$theme->id)}}" class="btn btn-primary btn-block">Protokoll anlegen</a>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-secondary btn-block" data-toggle="modal" data-target="#taskModal">
                                Aufgabe erstellen
                            </button>

                        </div>
                    @endif
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
@endpush
