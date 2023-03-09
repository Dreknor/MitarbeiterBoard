@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @if($site->title != "Start")
            <p>
                <a href="{{url('wiki')}}" class="btn btn-primary btn-link">zurück</a>
            </p>
        @else
            <div class="row">
                <div class="col-5">
                    <div class="card">
                        <div class="card-body ">
                            <form action="{{url('wiki/search')}}" method="post" class="form-inline">
                                @csrf
                                <input type="text" class="form-control w-75" name="search" placeholder="Suche ..." required>
                                <button type="submit" class="btn btn-xs btn-info p-2">
                                    <i class="fa fa-search" aria-hidden="true"></i> suchen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @can('edit wiki')
                    <div class="col-4">
                    <div class="card">
                        <div class="card-body ">
                            <form action="{{url('wiki/add')}}" method="post" class="form-inline">
                                @csrf
                                <input type="text" class="form-control w-75" placeholder="Neue Seite" name="title" required>
                                <button type="submit" class="btn btn-xs bg-gradient-directional-amber p-2">
                                    <i class="fa fa-add" aria-hidden="true"></i> erstellen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endcan
                <div class="col-3">
                    <div class="card">
                        <div class="card-body ">
                            <a href="{{url('wiki/all')}}" class="btn btn-block bg-gradient-directional-blue-grey">
                                Alle Seiten
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header border-bottom">
                @can('edit wiki')
                    <div class="pull-right">
                        <a href="{{url('wiki/create/'.$site->slug)}}" class="btn-link">
                            <i class="fa fa-edit"></i> bearbeiten
                        </a>
                    </div>
                @endcan

                <h5>

                    {{$site->title}}
                </h5>
            </div>
            <div class="card-body">
                {!! $site->text !!}
            </div>
            <div class="card-footer border-top bg-light">
                <div class="row">
                    <div class="col-auto">
                        Author: {{$site->author?->name}}
                    </div>
                    <div class="col-auto pull-right">
                        bearbeitet: {{$site->updated_at->format('d.m.Y H:i')}}
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection
