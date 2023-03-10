@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        @include('wiki.header')

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
