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
        <div class="container-fluid">
            <div class="card-columns">
                <div class="card">
                    <div class="card-header bg-gradient-directional-blue text-white">

                            <h5>
                                @can('create posts')
                                    <div class="d-inline pull-right">
                                        <a href="{{url('posts/create')}}" class="btn btn-sm btn-bg-gradient-x-blue-green">
                                            <i class="fa fa-plus"></i>
                                        </a>
                                    </div>
                                @endcan
                            Nachrichten
                            </h5>
                    </div>
                    <div class="card-body">
                                <div class="container-fluid">
                                    <div class="row">
                                        @if($posts->where('released', 1)->count() > 0)
                                            <div class="container-fluid">
                                                @foreach($posts as $post)
                                                    @if($post->released == 1 or $post->author_id == auth()->id())
                                                        @include('posts.post')
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p>
                                                Keine Nachrichten aktiv
                                            </p>
                                        @endif
                                    </div>
                                    <div class="row">
                                        <div class="container-fluid">
                                            {{$posts->links()}}
                                        </div>
                                    </div>

                                </div>
                            </div>
                </div>
                @if(auth()->user()->kuerzel != "")
                    <div class="row mt-2">
                        <div class="col-12">
                            @include('vertretungsplan.UserVertretungen')
                        </div>
                    </div>
                @endif
                @if($rosters->count() > 0)
                    @include('personal.rosters.homeView')
                @endif
                @can('view absences')
                    @include('absences.index')
                @endcan

                @include('tasks.tasksCard')
                @if(auth()->user()->can('view procedures'))
                    <div class="row mt-2">
                        <div class="col-12 mt-2">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        offene Prozessschritte
                                    </h5>
                                </div>
                                <div class="card-body">
                                    @if($steps and $steps->count() > 0)
                                        <ul class="list-group">
                                            @foreach($steps->sortByDate('endDate', 'desc') as $step)
                                                <li class="list-group-item">
                                                    <b>
                                                        {{$step->endDate->format('d.m.Y')}} - {{$step->name}}
                                                    </b>
                                                    <small>
                                                        {{$step->procedure->name}}
                                                    </small>
                                                    <div class="pull-right ml-1">
                                                        <a href="{{url('procedure/'.$step->procedure->id.'/start')}}">
                                                            <i class="fas fa-eye"></i> zum Prozess
                                                        </a>
                                                    </div>
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
                @endif



    </div>
@endsection

