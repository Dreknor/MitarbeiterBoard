@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <h6>
                    Datum der anzuzeigenden Protokolle wählen:
                </h6>
                <ul class="nav nav-tabs nav-fill">
                    @foreach($dates as $protocol_date)
                        <li class="nav-item ">
                            <a class="nav-link @if(request()->segment(3) == $protocol_date) active @endif" href="{{url(request()->segment(1).'/export/'.$protocol_date)}}">
                                {{\Carbon\Carbon::createFromFormat('Y-m-d',$protocol_date)->format('d.m.Y')}}
                            </a>
                        </li>
                    @endforeach
                    <li class="nav-item pull-right">
                        <input class="form-control" type="date" id="input">
                    </li>
                </ul>
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
                                    <p>
                                        {!! $protocol->protocol !!}
                                    </p>

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
            <div class="card-footer border border-info">
                <h6>Protokolle exportieren</h6>
                <p>
                    Zusätzliche Protokollearten auswählen
                </p>
                <form action="{{url(request()->segment(1).'/export/'.$date->format('Y-m-d').'/download')}}" method="post">
                    @csrf


                    <div class="form-row">
                        <div class="form-group">
                            <label for="closed">
                                <input type="checkbox" name="closed" class="custom-checkbox" checked>
                                Thema geschlossen
                            </label>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="changed">
                                <input type="checkbox" name="changed" class="custom-checkbox" checked>
                                Thema verschoben
                            </label>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="memory">
                                <input type="checkbox" name="memory" class="custom-checkbox checkbox" checked>
                                Änderung Themenspeicher
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="btn btn-success"> exportieren</button>
                    </div>

                </form>
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
