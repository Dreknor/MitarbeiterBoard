@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @include('wiki.header')
        <div class="row">
                @foreach($letters as $letter)
                <div class="col-12 col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">{{strtoupper($letter)}}</h5>
                            <ul class="list-group">
                                @foreach($sites as $slug => $site)
                                    @if(strtolower(substr($slug,0,1)) === $letter)
                                        <li class="list-group-item">
                                            <a href="{{url('wiki/'.$slug)}}" class="card-link">
                                                {{$slug}}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endforeach
        </div>
    </div>


@endsection
