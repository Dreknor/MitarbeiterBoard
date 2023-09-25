@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @include('wiki.header')
        <div class="row">
                <div class=" @if(isset($akt_site)) col-6 @else col-12 @endif">
                    <div class="card">
                        <div class="card-header border-bottom">
                            @if(!isset($akt_site))
                                @can('edit wiki')
                                    <div class="pull-right">
                                        <a href="{{url('wiki/create/'.$site->slug)}}" class="btn-link">
                                            <i class="fa fa-edit"></i> bearbeiten
                                        </a>
                                    </div>
                                @endcan
                            @endif
                            <h5>
                                {{$site->title}}
                            </h5>
                        </div>
                        <div class="card-body">
                            {!! $site->text !!}
                        </div>
                        @if($site->getMedia()->count() > 0 or $site->getMedia('files')->count() > 0)
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row">
                                            <div class="col">
                                                <h6>Bilder</h6>
                                            </div>
                                            <div class="col">
                                                @foreach($site->getMedia('default')->sortBy('name') as $media)
                                                    <a href="{{url('/image/'.$media->id)}}" target="_blank">
                                                        <img class="mx-auto img-thumbnail" src="{{url('/image/'.$media->id)}}" style="max-height: 200px; max-width: 200px" >
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row">
                                            <div class="col">
                                                <h6>Dateien</h6>
                                            </div>
                                            <div class="col">
                                                @foreach($site->getMedia('files')->sortBy('name') as $media)
                                                    <a href="{{url('/image/'.$media->id)}}" target="_blank">
                                                        {{$media->name}}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="card-footer border-top bg-light">
                            <div class="row">
                                <div class="col-auto">
                                    Author: {{$site->author?->name}}
                                </div>
                                <div class="col-auto pull-right">
                                    bearbeitet: {{$site->updated_at->format('d.m.Y H:i')}}
                                </div>
                                @if(!isset($akt_site))
                                    <div class="col pull-right">
                                        <a href="#" class="dropdown-toggle card-link" data-toggle="dropdown" aria-expanded="false">
                                            ältere Version
                                        </a>
                                        <ul class="dropdown-menu">
                                            @foreach($site->previous() as $version)
                                                <li>
                                                    <a class="dropdown-item" href="{{url('wiki/'.$version->slug.'/'.$version->id)}}">
                                                        {{$version->updated_at->format('d.m.Y H:i')}}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @if(isset($akt_site))
                <div class="col-6">
                    <div class="card">
                        <div class="card-header border-bottom">
                            @can('edit wiki')
                                <div class="pull-right">
                                    <a href="{{url('wiki/create/'.$akt_site->slug)}}" class="btn-link">
                                        <i class="fa fa-edit"></i> bearbeiten
                                    </a>
                                </div>
                            @endcan

                            <h5>

                                {{$akt_site->title}} (aktuell)
                            </h5>
                        </div>
                        <div class="card-body">
                            {!! $akt_site->text !!}
                        </div>
                        @if($site->getMedia()->count() > 0)
                            <div class="card-body">
                                @foreach($akt_site->getMedia()->sortBy('name') as $media)
                                    <a href="{{url('/image/'.$media->id)}}" target="_blank">
                                        <img class="mx-auto img-thumbnail" src="{{url('/image/'.$media->id)}}" style="max-height: 200px; max-width: 200px" >
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <div class="card-footer border-top bg-light">
                            <div class="row">
                                <div class="col-auto">
                                    Author: {{$site->author?->name}}
                                </div>
                                <div class="col-auto pull-right">
                                    bearbeitet: {{$site->updated_at->format('d.m.Y H:i')}}
                                </div>
                                <div class="col pull-right">
                                    <a href="#" class="dropdown-toggle card-link" data-toggle="dropdown" aria-expanded="false">
                                        ältere Version
                                    </a>
                                    <ul class="dropdown-menu">
                                        @foreach($site->previous() as $version)
                                            <li>
                                                <a class="dropdown-item" href="{{url('wiki/'.$version->slug.'/'.$version->id)}}">
                                                    {{$version->updated_at->format('d.m.Y H:i')}}
                                                </a>
                                            </li>
                                        @endforeach


                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>


@endsection
