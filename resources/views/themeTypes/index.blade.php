@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @include('themeTypes.createTyp')
                    <div class="card">
                        <div class="card-header" >
                            <h6>
                                   Vorhandene Typen
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                @foreach($typen as $typ)
                                    <li class="list-group-item">
                                        {{$typ->type}}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

@endsection
