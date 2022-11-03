@extends('layouts.app')


@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5>Wiederkehrende Themen</h5>
                <p>Wiederkehrende Themen sind Themen, die immer wieder neu bedacht werden müssen. Themen, die hier angelegt werden sind einem Monat zugeordnet und werden {{config('config.startRecurringThemeWeeksBefore')}} Wochen vor dem Start des Monats als neues Thema in der angegebenen Gruppe erstellt.</p>
            </div>
            @can('create themes')
                <div class="card-body">
                    <a href="{{url(request()->segment(1).'/themes/recurring/create')}}" class="btn btn-block btn-bg-gradient-x-blue-cyan">neues Thema</a>
                </div>
            @endcan
        </div>
        @foreach(config('config.months') as $key => $month)
            <div class="card">
                <div class="card-header">
                    <h6>{{$month}}</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @foreach($themes->where('month', $key) as $theme)
                            <a href="{{request()->url().'/'.$theme->id.'/edit'}}" class="list-group-item list-group-item-action border">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">{{$theme->theme}}</h5>
                                    <small>{{$theme->type->type}}</small>
                                </div>
                                <p class="mb-1">{{$theme->goal}}</p>
                                @if($theme->hasMedia())
                                    <p class="mb-1">Enthält Anhang</p>
                                @endif
                            </a>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>

@stop
