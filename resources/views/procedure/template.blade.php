@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <div class="card">
            @include('procedure.parts.nav')
            <div class="card-body border-top">
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

                                                <div class="pull-right mr-4">
                                                    <a href="{{url('procedure/'.$procedure->id.'/delete')}}" title="Vorlage löschen" class="text-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                                <div class="pull-right mr-4">
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
            <div class="card-body border-top" id="createProcedure">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-8">
                            <h6>
                                Vorlage anlegen
                            </h6>
                        </div>
                        <div class="col-4">
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
                    <form action="{{url('procedure/create/template')}}" method="post" class="form-horizontal" id="create">
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
@endpush
