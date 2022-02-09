@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-6 @if(request()->segment(1) == "procedure" and request()->segment(2) != "positions") bg-light border border-info text-center pt-2 @endif">
                        <a href="{{url('procedure/')}}" class=card-link">
                            @if(request()->segment(1) == "procedure" and request()->segment(2) != "positions")
                                <h5>
                                    aktive Prozesse
                                </h5>
                            @else
                                <h6>
                                    aktive Prozesse
                                </h6>
                            @endif

                        </a>
                    </div>

                        <div class="col-6 @if(request()->segment(1) == "procedure" and request()->segment(2) == "positions") bg-light border border-info text-center pt-2  @endif">
                            <a href="{{url('procedure/positions')}}" class=card-link">
                                @if(request()->segment(1) == "procedure" and request()->segment(2) == "positions")
                                    <h5>
                                        Positionen
                                    </h5>
                                @else
                                    <h6>
                                        Positionen
                                    </h6>
                                @endif

                            </a>
                        </div>

                </div>
            </div>
        </div>
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
                                    <button data-target="#createProcedure" data-toggle="collapse" class="btn btn-outline-info">
                                        <i class="fas fa-project-diagram"></i>
                                        <div class="d-none d-sm-inline-block">
                                            Prozessvorlage erstellen
                                        </div>
                                    </button>
                                </div>
                            @endif

                        </div>
                        <div class="col">
                            <div class=" pull-right">
                                <button  data-toggle="modal" data-target="#CategoryModal" class="btn btn-outline-info">
                                    <i class="fas fa-folder-plus"></i>
                                    <div class="d-none d-sm-inline-block">
                                        neue Kategorie erstellen
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body collapse border-top" id="createProcedure" data-toggle="collapse">
                <div class="container-fluid">
                    <form action="{{url('procedure/create/template')}}" method="post" class="form-horizontal">
                        @csrf
                        <div class="form-row">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label for="name">
                                    Name des Prozesses
                                </label>
                                <input type="text" name="name" id="name" value="{{old('name')}}" class="form-control p-2">
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <label for="name">
                                    Kategorie
                                </label>
                                <select name="category_id" class="custom-select" required>
                                    <option disabled selected> </option>
                                    @foreach($categories as $category)
                                        <option value="{{$category->id}}">
                                            {{$category->name}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-12">
                                <label for="description">
                                    Beschreibung
                                </label>
                                <textarea name="description" id="description" rows="6" class="form-control">
                                {{old('description')}}
                            </textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <button type="submit" class="btn btn-success btn-block">
                                anlegen
                            </button>
                        </div>
                    </form>
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
            <div class="card-body border-top border-info">
                <h6>Vorlagen</h6>
                <div class="row">
                    @foreach($categories as $category)
                        <div class="col-md-6 col-sm-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6>
                                        {{$category->name}}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group">
                                        @foreach($proceduresTemplate->where('category_id', $category->id) as $procedure)
                                            <li class="list-group-item">
                                                {{$procedure->name}}
                                                <div class="pull-right">
                                                    <a href="{{url('procedure/'.$procedure->id.'/edit')}}" title="Vorlage bearbeiten">
                                                        <i class="fas fa-pen"></i>
                                                    </a>
                                                </div>
                                                <div class="pull-right mr-4">
                                                    <a href="{{url('procedure/'.$procedure->id.'/start')}}" title="Prozess beginnen">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>
        @endif


    </div>
</div>

@endsection

@push('modals')

    <div class="modal" tabindex="-1" role="dialog" id="CategoryModal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Neue Kategorie erstellen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('procedure/categories')}}" method="post" class="form-horizontal">
                        @csrf
                        <div class="form-row">
                            <label for="name">
                                Name der Kategorie
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


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
