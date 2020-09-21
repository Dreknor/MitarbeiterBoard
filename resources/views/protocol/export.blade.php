@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        Datum wählen:
                    </div>
                    <div class="col-md-6 col-sm-12">
                            <input class="form-control" type="date" id="input">
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(!is_null($themes) and $themes->count() > 0)
        <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    Protokolle der Gruppe {{request()->segment(1)}} vom {{$date->format('d.m.Y')}}
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>

                        </th>
                        <th>
                            Thema
                        </th>
                        <th>
                            Protokoll
                        </th>
                        <th>
                            Aufgabe
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($themes as $theme)
                        <tr>
                            <td>
                                {{$loop->iteration}}
                            </td>
                            <td>
                                <p>
                                    <b>
                                        {{$theme->theme}}
                                    </b>
                                </p>
                                <p>
                                    <a href="{{url($theme->group->name.'/themes/'.$theme->id)}}">anzeigen</a>
                                </p>
                                <p>
                                    <!--
                                    {!! $theme->information !!}
                                        -->
                                </p>
                            </td>
                            <td>
                                @foreach($theme->protocols->filter(function ($value) use ($date){
                                    return $value->created_at->format('Y-m-d') == $date->format('Y-m-d');
                                })  as $protocol)
                                    {!! $protocol->protocol !!}
                                @endforeach
                            </td>
                            <td>
                                <ul class="list-group">
                                    @foreach($theme->tasks->filter(function ($value) use ($date){
                                        return $value->created_at->format('Y-m-d') == $date->format('Y-m-d');
                                    })  as $task)
                                       <li class="list-group-item">
                                           {{$task->taskable->name}} - {{$task->task}}
                                       </li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    @else
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        Protokolle der Gruppe {{request()->segment(1)}} vom {{$date->format('d.m.Y')}}
                    </h5>
                </div>
                <div class="card-body">
                    <p>
                        Keine Protokolle gefunden
                    </p>
                </div>
            </div>
        </div>
    @endif
@endsection
@push('js')
    <script>
        var input = document.getElementById('input');
        var pathArray = window.location.pathname.split('/');
        pathArray.shift();
        if (pathArray[pathArray.length-1]!= 'export'){
            pathArray.pop();

        }

        document.getElementById('input').onchange = function (event) {
            var path = "";

            pathArray.forEach(element => path =  path + '/' + element);
            path = path + '/' + this.value;

            window.location.href =  path;
        }
    </script>
@endpush
