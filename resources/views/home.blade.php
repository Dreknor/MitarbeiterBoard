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
        <div class="row">
            <div class="col-md-12 col-lg-8 ">
                <div class="container-fluid">
                    <div class="row">
                        <div class="card w-100">
                            <div class="card-header bg-gradient-directional-blue text-white">
                                <div class="pull-right">
                                    <a href="{{url('posts/create')}}" class="btn btn-bg-gradient-x-blue-green">
                                        <i class="fa fa-plus"></i>
                                    </a>
                                </div>
                                <h5>
                                    Nachrichten
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="container-fluid">
                                            @foreach($posts as $post)
                                                @if($post->released == 1 or $post->author_id == auth()->id())
                                                    @include('posts.post')
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="container-fluid">
                                            {{$posts->links()}}
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="col-md-12 col-lg-4 ">
                @can('view absences')
                    <div class="row mt-1">
                        <div class="col-12">
                            @include('absences.index')
                        </div>
                    </div>
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

                @if(auth()->user()->kuerzel != "")
                    <div class="row mt-2">
                        <div class="col-12">
                            @include('vertretungsplan.UserVertretungen')
                        </div>
                    </div>
                @endif

                </div>
            </div>

    </div>
@endsection

