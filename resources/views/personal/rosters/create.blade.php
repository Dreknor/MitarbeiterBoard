@extends('layouts.app')

@section('title')
    neuer Dienstplan
@endsection




@section('content')
    <form action="{{url('/roster')}}" method="post" class="form form-horizontal" autocomplete="off">
        @csrf
        <div class="container-fluid">
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title">
                        neuen Dienstplan anlegen ({{$department->name}})
                    </h5>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label class="text-danger">gültig ab</label>
                                <input type="date" class="form-control border-input"
                                       value="{{\Carbon\Carbon::now()->next('monday')->format('Y-m-d')}}"
                                       name="start_date" required autocomplete="off" value="{{old('start_date')}}">
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label class="">Kommentar</label>
                                <input type="text" class="form-control border-input" placeholder="Kommentar"
                                       name="comment" autocomplete="off" value="{{old('comment')}}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label class="text-danger">Typ</label>
                                <select name="type" class="custom-select" required>
                                    <option value="normal">normal</option>
                                    <option value="template">Vorlage</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="">Benutze Vorlage vom</label>
                            <select name="used_template" class="custom-select">
                                <option></option>
                                @foreach($templates as $template)
                                    <option
                                        value="{{$template->id}}">{{$template->start_date->format('d.m.Y')}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="">Bereich</label>
                            <select name="department_id" class="custom-select">
                                <option value="{{$department->id}}">{{$department->name}}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-block" id="btn-save">speichern</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('js')



@endpush
