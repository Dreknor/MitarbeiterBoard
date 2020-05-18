@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                bearbeite Thema
            </h5>
        </div>
        <div class="card-body border-top">
            <form method="post" class="form form-horizontal" action="{{url('themes/'.$theme->id)}}" id="editForm">
                @csrf
                @method('put')
                <div class="form-row">
                    <div class="col-sm-12 col-md-12 col-lg-4">
                        <label for="theme">Thema</label>
                        <input type="text" class="form-control" id="theme" name="theme" required autofocus value="{{old('theme', $theme->theme)}}" @if ($theme->priority) disabled @endif>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-4">
                        <label for="type">Typ</label>
                        <select name="type" id="type" class="custom-select" required>
                            <option disabled></option>
                            @foreach($types as $type)
                                <option value="{{$type->id}}" data-needsprotocol="{{$type->needsProtocol}}" @if (old('type', $theme->type_id) == $type->id) selected @endif>{{$type->type}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-4">
                        <label for="duration">Dauer</label>
                        <input type="number" class="form-control" id="duration" name="duration" required min="5" max="240" step="5" value="{{old('duration', $theme->duration)}}">
                    </div>
                </div>
                <div class="form-row">
                    <label for="goal">Ziel</label>
                    <input type="text" class="form-control" id="goal" name="goal" required value="{{old('goal', $theme->goal)}}">
                </div>
                <div class="form-row">
                    <label for="information">Informationen</label>
                    <textarea class="form-control" id="information" name="information">
                        {{old('information', $theme->information)}}
                    </textarea>
                </div>
            </form>
        </div>
        <div class="card-footer">
            <button type="submit" form="editForm" class="btn btn-warnig btn-block">speichern</button>
        </div>
    </div>
</div>
@stop