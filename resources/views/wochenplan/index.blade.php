@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h6>
                    Wochenpläne
                    <div class="d-inline pull-right">
                        <a href="{{url(request()->segment(1).'/wochenplan/create')}}" class="card-link">neuer Plan</a>
                    </div>
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Klasse</th>
                            <th>gültig von</th>
                            <th>gültig bis</th>
                            <th>Bezeichnung</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($wochenplaene as $wochenplan)
                            <tr>
                                <td>
                                    @foreach($wochenplan->klassen as $klasse)
                                        {{$klasse->name}}@if(!$loop->last),@endif
                                    @endforeach
                                </td>
                                <td>
                                    {{$wochenplan->gueltig_ab->format('d.m.Y')}}
                                </td>
                                <td>
                                    {{$wochenplan->gueltig_bis->format('d.m.Y')}}
                                </td>
                                <td>
                                    {{$wochenplan->name}}
                                </td>
                                <td>
                                    <a href="{{url(request()->segment(1)."/wochenplan/".$wochenplan->id)}}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">
                                {{$wochenplaene->links()}}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

@endsection
