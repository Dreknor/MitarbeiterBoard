@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <p>
                <h5 class="card-title">
                    Willkommen {{auth()->user()->name}}
                </h5>
            </p>
        </div>
        <div class="row mt-2">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            anstehende Aufgaben
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($tasks and $tasks->count() > 0)
                            <ul class="list-group">
                                @foreach($tasks->sortByDate('date', 'desc') as $task)
                                    <li class="list-group-item">

                                        <b>{{$task->date->format('d.m.Y')}} - {{$task->taskable->name}}: </b>
                                            {{$task->task}}
                                        @if ($task->taskable->name == auth()->user()->name)
                                            <div class="pull-right">
                                                <a href="{{url('tasks/'.$task->id.'/complete')}}">
                                                    <i class="far fa-check-square"></i> erledigt
                                                </a>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p>
                                Es stehen keine Aufgaben an
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="container-fluid">
                <div class="card-deck">
                    @foreach($groups as $group)
                        @if($loop->index%2 == 0)
                            <div class="w-100 d-none d-sm-block d-md-none"><!-- wrap every 2 on sm--></div>
                        @elseif($loop->index%3 == 0)
                            <div class="w-100 d-none d-md-block d-lg-none"><!-- wrap every 3 on md--></div>
                        @endif
                        <div class="card m-1">
                            <div class="card-header" style="background-color: {{$colors[$loop->index]}}">
                                <h5 class="card-title">
                                    {{$group->name}}
                                </h5>
                                @if($group->enddate != "")
                                    <p class="small">
                                        Erstellt von {{$group->creator->name}} und offen bis {{$group->enddate->format('d.m.Y')}} (noch {{$group->enddate->diffInDays(\Carbon\Carbon::now())}} Tage)
                                    </p>
                                @endif
                            </div>
                            <div class="card-body">
                                <p>
                                    <b>
                                        nächste Besprechung:
                                    </b>
                                    {{optional(optional($group->themes->sortBy('date')->filter(function ($theme){
                                       return $theme->completed == 0 and $theme->date->startOfDay()->greaterThanOrEqualTo(\Carbon\Carbon::now()->startOfDay());
                                    })->first())->date)->format('d.m.Y')}}
                                </p>
                            </div>
                            <div class="card-body h-100">
                                <p>
                                    <b>
                                        Themen:
                                    </b>
                                </p>
                                <ul class="list-group">
                                    @foreach($group->themes->sortBy('date')->filter(function ($theme){
                                           return $theme->completed == 0 and $theme->date->startOfDay()->greaterThanOrEqualTo(\Carbon\Carbon::now()->startOfDay());
                                        }) as $theme)
                                        <li class="list-group-item">
                                            {{$theme->theme}}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-footer">
                                <a href="{{url($group->name."/themes")}}" class="btn btn-primary btn-block">
                                    anzeigen
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

    </div>
@endsection
