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
                            <a href="{{url('inventory/items/'.$item->id.'/edit')}}" title="{{$item->name}} bearbeiten" class="pull-right">
                                <i class="fas fa-edit"></i>
                            </a>
                    </div>
                    <div class="card-body">
                            {{$item->descripton}}
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
