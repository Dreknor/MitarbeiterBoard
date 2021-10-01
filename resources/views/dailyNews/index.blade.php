@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @include('dailyNews.createNews')
                    <div class="card">
                        <div class="card-header" id="headingOne">
                            <h6>
                                    Aktuelle News
                            </h6>
                        </div>
                        <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordion">
                            <div class="card-body">
                                @foreach($dailyNews->pluck('date_start')->unique() as $date )
                                    <p class="mt-2">
                                        <b>
                                            {{$date->format('d.m.Y')}}
                                        </b>
                                    </p>
                                <div class="row">
                                    <div class="col-12">
                                        <ul class="list-group">
                                            @foreach($dailyNews->filter(function ($item) use ($date) { if(($item->date_start == $date and $item->date_end == null) or ($date->between($item->date_start, $item->date_end) and $item->date_end != null)){ return $item;}}) as $news)
                                                <li class="list-group-item">
                                                    @if($news->date_end != null)
                                                        <div class="d-inline">
                                                            {{$news->date_start->format('d.m.Y')}} - {{$news->date_end->format('d.m.Y')}}:
                                                        </div>
                                                    @endif
                                                    {{$news->news}}
                                                    <div class="pull-right">
                                                        <form action="{{url('dailyNews/'.$news->id)}}" method="post" class="form-inline">
                                                            @csrf
                                                            @method('delete')
                                                            <button type="submit" class="btn-link btn-danger">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

@endsection
