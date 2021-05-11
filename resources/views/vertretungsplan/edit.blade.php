@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @include('vertretungsplan.create')

                    <div class="card">
                        <div class="card-body">
                            Abrufbar unter: <a href="{{url('vertretungsplan')}}" target="_blank">{{url('vertretungsplan')}}</a>
                        </div>
                    </div>

                <div id="accordion">
                    <div class="card">
                        <div class="card-header" id="headingOne">
                            <h6>
                                <button class="btn btn-link" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    Aktuelle Vertretungen
                                </button>
                            </h6>
                        </div>
                        <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordion">
                            <div class="card-body">
                                @foreach($vertretungen_aktuell->pluck('date')->unique() as $date )
                                   <div class="row">
                                       <div class="col-12">
                                           <b>
                                               {{$date->format('d.m.Y')}}
                                           </b>
                                           <div class="pull-right ">
                                               <a href="{{url('vertretungen/'.$date->format('Y-m-d').'/generate-doc')}}" class="btn btn-sm">
                                                   <i class="fas fa-file-word"></i>
                                               </a>
                                           </div>
                                       </div>
                                   </div>
                                <div class="row">
                                    <div class="col-12">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                            <tr>
                                                <th>Datum</th>
                                                <th>Stunde</th>
                                                <th>Klasse</th>
                                                <th>Fächer</th>
                                                <th>neuer Lehrer</th>
                                                <th>Kommentar</th>
                                                <th></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($vertretungen_aktuell->where('date', $date) as $vertretung)
                                                <tr>
                                                    <td>{{$vertretung->date->format('d.m.Y')}}</td>
                                                    <td>{{$vertretung->stunde}}</td>
                                                    <td>{{$vertretung->klasse->name}}</td>
                                                    <td>{{$vertretung->altFach}} @if($vertretung->neuFach) -> {{$vertretung->neuFach}}@endif</td>
                                                    <td>{{optional($vertretung->lehrer)->name}}</td>
                                                    <td>{{$vertretung->comment}}</td>
                                                    <td>
                                                        <form action="{{url('vertretungen/'.$vertretung->id)}}" method="post" class="form-inline">
                                                            @csrf
                                                            @method('delete')
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                        <a href="{{url('vertretungen/'.$vertretung->id.'/copy')}}" class="btn btn-sm btn-primary">
                                                            <i class="far fa-copy"></i>
                                                        </a>

                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header" id="headingTwo">
                            <h6 class="mb-0">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    alte Vertretungen
                                </button>
                            </h6>
                        </div>
                        <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#accordion">
                            <div class="card-body">
                                @foreach($vertretungen_alt->pluck('date')->unique() as $date )
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
                                        @foreach($vertretungen_alt->where('date', $date) as $vertretung)
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
            </div>



@endsection
