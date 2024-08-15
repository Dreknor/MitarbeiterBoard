@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="card">
        @include('procedure.parts.nav')
        @if(request()->segment(1) == "procedure" and request()->segment(2) == "positions")
            <div class="card-body border-top">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12 col-sm-12">
                            <div class=" pull-right">
                                <button  data-toggle="modal" data-target="#positionModal" class="btn btn-outline-info">
                                    <i class="fas fa-user"></i>
                                    <div class="d-none d-sm-inline-block">
                                        neue Position erstellen
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('procedure.postions')
        @else
            <div class="card-body border-top">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            @if($categories != null and count($categories)>0)
                                <div class=" pull-right">
                                    <a href="{{url('procedure/template#create')}}" class="btn btn-outline-info">
                                        <i class="fas fa-folder-plus"></i>
                                        <div class="d-none d-sm-inline-block">
                                            Vorlage anlegen
                                        </div>
                                    </a>

                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body border-top">
                <h6>
                    aktive Prozesse
                </h6>
                <ul class="list-group">
                    @foreach($procedures as $procedure)
                        <li class="list-group-item">
                            {{$procedure->name}}
                            <div class="pull-right ml-4">
                                <a href="{{url('procedure/'.$procedure->id.'/ends')}}" class="card-link text-danger" title="Prozess beenden">
                                    <i class="far fa-times-circle"></i>
                                </a>
                            </div>
                            <div class="pull-right ml-2">
                                <a href="{{url('procedure/'.$procedure->id.'/start')}}" class="card-link">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif


    </div>
</div>

@endsection
@push('modals')


    <div class="modal" tabindex="-1" role="dialog" id="positionModal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Neue Position erstellen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('procedure/position')}}" method="post" class="form-horizontal">
                        @csrf
                        <div class="form-row">
                            <label for="name">
                                Name der Position
                            </label>
                            <input name="name" type="text" class="form-control">
                        </div>
                        <div class="form-row">
                            <button type="submit" class="btn btn-success btn-block">
                                anlegen
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


@endpush

@push('js')

@endpush
