@extends('layouts.app')


@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h6>
                    Themenspeicher
                </h6>
            </div>
            <div class="card-body">
                @can('create themes')
                    <div class="card-body">
                        <a href="{{url(request()->segment(1).'/themes/create/speicher')}}" class="btn btn-bg-gradient-x-blue-cyan btn-block">neues Thema</a>
                    </div>
                @endcan
            </div>
        </div>


        @if (count($themes) == 0)
            <div class="card">
                <div class="card-body">
                    <p>
                        Es gibt keine gemerkten Themen
                    </p>
                </div>
            </div>
        @else

            <div class="card" >
                <div class="card-body">
                    <div class="table-responsive-sm table-responsive-md">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Von</th>
                                <th>Thema</th>
                                <th>Datum</th>
                            </tr>
                            </thead>
                            <tbody class="connectedSortable" >
                            @foreach($themes as $theme)
                                <tr id="{{$theme->id}}" @if($theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ) class="bg-warning" @endif>
                                    <td>
                                        {{$theme->ersteller->name}}
                                    </td>
                                    <td>
                                        {{$theme->theme}}
                                    </td>

                                    <td>
                                        {{$theme->date->format('d.m.Y')}}
                                    </td>
                                    <td>
                                        <a href="{{url(request()->segment(1)."/themes/$theme->id/activate")}}">
                                            <i class="far fa-upload"></i> aktivieren
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

@stop

@push('js')

@endpush

@push('css')

@endpush
