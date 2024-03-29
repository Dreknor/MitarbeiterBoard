@extends('layouts.app')

@section('content')
    <a href="{{url('inventory/items/')}}" class="btn btn-primary btn-link">zurück</a>

    <div class="card">
            <div class="row">
                <div class="col-sm-12 col-md-9">
                    <div class="card-header border-bottom">
                            <h5 class="d-inline">
                                {{$item->name}} ({{$item->status}})
                            </h5>
                            @if (app()->environment('local'))
                                <a href="{{url('inventory/item/'.$item->uuid)}}" title="{{$item->name}} " class="pull-right mr-2">
                                    <i class="fas fa-eye"></i>
                                </a>
                            @endif
                            <a href="{{url('inventory/items/'.$item->id.'/edit')}}" title="{{$item->name}} bearbeiten" class="pull-right mr-2">
                                <i class="fas fa-edit"></i>
                            </a>

                    </div>
                    <div class="card-body">
                            {{$item->descripton}}<br>
                            <p>
                                Inventarnummer: {{$item->oldInvNumber?:$item->uuid}}
                            </p>
                    </div>
                    <div class="card-body">
                        <div class="card-columns">
                            <div class="card bg-info">
                                <div class="card-header">
                                    <b>
                                        Kategorie
                                    </b>
                                </div>
                                <div class="card-body">
                                    {{optional($item->category)->name}}
                                </div>
                            </div>
                            @if (count($item->getMedia('invoice')) > 0)
                                <div class="card">
                                    <div class="card-header">
                                        <b>
                                            Rechnung
                                        </b>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group">
                                            @foreach($item->getMedia('invoice') as $media)
                                                <li class="list-group-item  list-group-item-action ">
                                                    <a href="{{url('/image/'.$media->id)}}" target="_blank" class="mx-auto ">
                                                        <i class="fas fa-file-download"></i>
                                                        {{$media->name}} (erstellt: {{$media->created_at->format('d.m.Y H:i')}} Uhr)
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            <div class="card bg-warning">
                                <div class="card-header">
                                    <b>
                                        Raum
                                    </b>
                                </div>
                                <div class="card-body">
                                    {{optional($item->location)->name}}
                                </div>
                            </div>
                            <div class="card bg-primary">
                                <div class="card-header">
                                    <b>
                                        Lieferant
                                    </b>
                                </div>
                                <div class="card-body">
                                    <p>
                                        {{optional($item->lieferant)->name}}
                                    </p>
                                    <p>
                                        <b>
                                            Datum:
                                        </b> {{optional($item->date)->format('d.m.Y')}}
                                    </p>
                                    <p>
                                        <b>
                                            Preis:
                                        </b> {{number_format($item->price,2)}} €
                                    </p>
                                    <p>
                                        <b>
                                            Anzahl:
                                        </b> {{$item->number}}
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card-body">
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(50)->generate(url('inventory/items/'.$item->uuid)); !!}
                    </div>
                </div>
                <div class="col-sm-12 col-md-3">
                    @if (count($item->getMedia()) > 0)
                        <div id="carouselControls" class="carousel slide" data-ride="carousel">
                            <div class="carousel-inner">
                                    @foreach($item->getMedia() as $media)
                                        <div class="carousel-item  @if($loop->first) active @endif">
                                            <img src="{{url('/image/'.$media->id)}}" class="d-block w-100 " >
                                        </div>
                                    @endforeach
                                        @if(count($item->getMedia())>1)
                                            <a class="carousel-control-prev" href="#carouselControls" role="button" data-slide="prev">
                                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                <span class="sr-only">Previous</span>
                                            </a>
                                            <a class="carousel-control-next" href="#carouselControls" role="button" data-slide="next">
                                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                <span class="sr-only">Next</span>
                                            </a>
                                        @endif


                            </div>
                        @else
                            <img src="{{asset('img/items.png')}}">
                        @endif
                    </div>
                </div>
        </div>
    </div>
@endsection
