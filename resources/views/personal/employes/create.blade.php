@extends('layouts.app')

@section('title')
    neuer Mitarbeiter
@endsection

@section('content')
    <form action="{{url('/employes/')}}" method="post" class="form form-horizontal" autocomplete="off">
        @csrf
        <div class="container-fluid">
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title">
                        neuen Mitarbeiter anlegen
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
                                    <label class="text-danger">Familienname</label>
                                    <input type="text" class="form-control border-input" placeholder="Familienname" name="familienname" required autocomplete="off" value="{{old('familienname')}}">
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label class="text-danger">Vorname</label>
                                    <input type="text" class="form-control border-input" placeholder="Vorname" name="vorname" required autocomplete="off" value="{{old('vorname')}}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label class="text-danger">Geburtsdatum</label>
                                    <input type="date" class="form-control border-input" name="geburtstag" required autocomplete="off" value="{{old('geburtstag')}}">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label class="text-danger">Geschlecht</label>
                                    <select name="geschlecht" class="custom-select" required>
                                        <option disabled selected></option>
                                        <option value="männlich">männlich</option>
                                        <option value="weiblich">weiblich</option>
                                        <option value="anderes">anderes</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label>Geburtsname</label>
                                    <input type="text" class="form-control border-input" placeholder="Geburtsname" name="geburtsname" autocomplete="off" value="{{old('geburtsname')}}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label >Geburtsort</label>
                                    <input type="text" class="form-control border-input" placeholder="Geburtsort" name="geburtsort"   value="{{old('geburtsort')}}">
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label>Sozialversicherungsnummer</label>
                                    <input type="text" class="form-control border-input" placeholder="Sozialversicherungsnummer" name="sozialversicherungsnummer"  autocomplete="off" value="{{old('sozialversicherungsnummer')}}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label class="text-danger">Schwerbehindert?</label>
                                    <select name="schwerbehindert" class="custom-select" required>
                                        <option value="0">nein</option>
                                        <option value="1">ja</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label class="text-danger">Staatsangehörigkeit</label>
                                    <input type="text" class="form-control border-input" value="deutsch" name="staatsangehoerigkeit" required autocomplete="off" value="{{old('staatsangehoerigkeit')}}">
                                </div>
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
