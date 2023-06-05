@extends('layouts.app')

@section('content')
    <div class="container-fluid">
            <p>
                <a href="{{url('wiki')}}" class="btn btn-primary btn-link">zurück</a>
            </p>
        <div class="card">
            <div class="card-header border-bottom">
                <h5>
                    Suchergebnisse
                </h5>
            </div>
            <div class="card-body">
                @if($sites != null)
                    <div class="list-group">
                        @foreach($sites as $site)
                            <a href="{{url('wiki/'.$site->slug)}}" class="list-group-item list-group-item-action flex-column align-items-start">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">
                                        {{$site->title}}
                                    </h5>
                                    <small>

                                    </small>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p>
                        Keine Übereinstimmungen gefunden
                    </p>
                @endif

            </div>
        </div>
    </div>


@endsection
