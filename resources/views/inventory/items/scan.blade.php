@extends('layouts.layout')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="d-inline">
                        {{$item->name}} ({{$item->status}})
                    </h5>
                </div>
                <div class="card-body">
                    {{$item->descripton}}
                </div>
                <div class="card-body">
                    <form action="{{url('inventory/item/'.$item->uuid)}}" method="post" class="form-horizontal">
                        @csrf
                        <div class="form-row">
                            <div class="col-3">
                                <b>
                                    Raum
                                </b>
                            </div>
                            <div class="col">
                                <select name="location_id" id="category" class="custom-select">
                                    @foreach($locations as $location)
                                        <option value="{{$location->id}}"  @if($item->location->id == $location->id) selected @endif>
                                            {{$location->name}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row mt-2">
                            <div class="col-3">
                                <b>
                                    Zustand
                                </b>
                            </div>
                            <div class="col">
                                <select name="status" id="status" class="custom-select">
                                    <option value="neu"  @if($item->status == 'neu') selected @endif>neuwertig</option>
                                    <option value="abgenutzt"  @if($item->status == 'abgenutzt') selected @endif>abgenutzt</option>
                                    <option value="defekt"  @if($item->status == 'defekt') selected @endif>defekt</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row mt-2">
                            <button type="submit" class="btn btn-success btn-block">aktualisieren</button>
                        </div>
                    </form>
                </div>

                </div>
            </div>
            <div class="col-auto">
                <div class="card">
                    <div class="card-body">
                        @if (count($item->getMedia()) > 0)
                            <div id="carouselControls" class="carousel slide" data-ride="carousel">
                            <div class="carousel-inner">
                                @foreach($item->getMedia() as $media)
                                    <div class="carousel-item  @if($loop->first) active @endif">
                                        <img src="{{url('/image/'.$media->id)}}" class="d-block"  style="width: 200px;  height: auto;">
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
                        </div>
                        @else
                            <img src="{{asset('img/items.png')}}"  style="height:350px;">
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
