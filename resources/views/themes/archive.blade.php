@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Themen</h5>
            </div>
            <div class="card-body">
                <a href="{{url('themes/create')}}" class="btn btn-block btn-primary">neues Thema</a>
            </div>
            <div class="card-body">
                {{$themes->links()}}
            </div>
        </div>
        @if (count($themes) == 0)
            <div class="card">
                <div class="card-body">
                    <p>
                        Es gibt keine offenen Themen
                    </p>
                </div>
            </div>
        @else
            @foreach($themes as $day => $dayThemes)
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            {{$day}}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm table-responsive-md">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Von</th>
                                    <th>Thema</th>
                                    <th>Typ</th>
                                    <th style="max-width: 30%;">Ziel</th>
                                    <th>Dauer</th>
                                    <th>Priorität</th>
                                    <th>Informationen</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($dayThemes as $theme)
                                    <tr>
                                        <td>
                                            {{$theme->ersteller->name}}
                                        </td>
                                        <td>
                                            {{$theme->theme}}
                                        </td>

                                        <td>
                                            {{$theme->type->type}}
                                        </td>
                                        <td>
                                            {{$theme->goal}}
                                        </td>
                                        <td>
                                            {{$theme->duration}} Minuten
                                        </td>
                                        <td id="priority_{{$theme->id}}">
                                                <div class="progress">
                                                    <div class="progress-bar amount" role="progressbar" style="width: {{100-$theme->priority}}%;" ></div>
                                                </div>
                                        </td>
                                        <td>
                                            <a href="{{url("themes/$theme->id")}}">anzeigen</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

@stop

@push('js')

@endpush

@push('css')

@endpush
