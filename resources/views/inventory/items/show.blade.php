@extends('layouts.app')
@section('content')
    <div class="card">
            <div class="row">
                <div class="col-sm-12 col-md-6">
                    <div class="card-header">
                        <div class="container-fluid">
                            <h5>
                                {{$item->name}}
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                            {{$item->descripton}}
                    </div>
                    <div class="card-body">
                        
                    </div>
                </div>
                <div class="col-sm-12 col-md-6">
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
                            <img src="{{asset('img/items.png')}}" style="width: 60%" class="pull-right">

                        @endif
                    </div>
                </div>

    </div>
@endsection
