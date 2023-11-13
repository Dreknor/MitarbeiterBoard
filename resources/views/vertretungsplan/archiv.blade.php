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
                    @foreach($vertretungen->pluck('date')->unique() as $date )
                        <p class="">
                            <b>
                                {{$date->format('d.m.Y')}}
                            </b>
                        </p>
                        <table class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Stunde</th>
                                <th>Klasse</th>
                                <th>Fächer</th>
                                <th>Lehrer</th>
                                <th>Kommentar</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($vertretungen->where('date', $date) as $vertretung)
                                <tr>
                                    <td>{{$vertretung->date->format('d.m.Y')}}</td>
                                    <td>{{$vertretung->stunde}}</td>
                                    <td>{{$vertretung->klasse->name}}</td>
                                    <td>{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                                    <td>{{optional($vertretung->lehrer)->name}}</td>
                                    <td>{{$vertretung->comment}}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
