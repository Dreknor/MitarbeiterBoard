@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header" id="headingTwo">
                <h6 class="mb-0">
                        alte Vertretungen
                </h6>
            </div>
            <div class="card-body " id="exportOld">
                @include('vertretungsplan.export')
            </div>
            <div class="card-body">
                <h4>Statistik</h4>
                @if(count($auswertung) > 0)
                    <table class="table table-bordered table-striped">
                        <tbody>
                        @foreach($auswertung as $key => $data)
                            <tr>
                                <td>{{$key}}</td>
                                <td>{{$data}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-body">
                <table class="table table-striped" id="tableVertretungen">
                    <thead class="thead-light">
                        <tr>
                            <th>Datum</th>
                            <th>Typ</th>
                            <th>Stunde</th>
                            <th>Klasse</th>
                            <th>Fache (alt)</th>
                            <th>Fach (neu)</th>
                            <th>Lehrer</th>
                            <th>Kommentar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vertretungen->sortByDesc('date') as $vertretung)
                            <tr>
                                <td>{{$vertretung->date->format('Y-m-d')}}</td>
                                <td>{{$vertretung->type}}</td>
                                <td>{{$vertretung->stunde}}</td>
                                <td>{{$vertretung->klasse->name}}</td>
                                <td>{{$vertretung->altFach}}</td>
                                <td> {{$vertretung?->neuFach}}</td>
                                <td>{{optional($vertretung->lehrer)->name}}</td>
                                <td>{{$vertretung->comment}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection


@push('js')
    <script src="{{asset('DataTables/datatables.min.js')}}"></script>

    <script>
        $(document).ready( function () {
            $('#tableVertretungen').DataTable();
        } );
    </script>

@endpush

@section('css')
    <link href="{{asset('DataTables/datatables.min.css')}}" rel="stylesheet" />


@endsection
