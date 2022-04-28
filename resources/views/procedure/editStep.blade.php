@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h6>
                Schritt bearbeiten
            </h6>
        </div>
        <div class="card-body">
            <form action="{{url('procedure/step/'.$step->id)}}" method="post" class="form-horizontal" id="stepForm">
                @csrf
                @method('put')
                <div class="form-row">
                    <div class="col-12">
                        <label for="name">
                            Bezeichnung des Schrittes
                        </label>
                        <input  id="name" name="name" type="text" class="form-control" value="{{old('name', $step->name)}}" maxlength="60" required>
                    </div>
                </div>
                <div class="form-row">
                    <label for="description">
                        Beschreibung
                    </label>
                    <textarea name="description" id="description" rows="6" class="form-control">
                {{old('description', $step->description)}}
            </textarea>
                </div>
                <div class="form-row">
                    <div class="col-md-8 col-sm-12">
                        <label for="position_id">
                            Verantwortliche Position
                        </label>
                        <select name="position_id" class="custom-select" required>
                            <option disabled> </option>
                            @foreach($positions as $position)
                                <option value="{{$position->id}}" @if($step->position_id == $position->id) selected @endif>
                                    {{$position->name}}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <label for="durationDays">
                            Dauer in Tagen
                        </label>
                        <input type="number" class="form-control" required min="1" step="1" name="durationDays" value="{{old('durationDays', $step->durationDays)}}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col-12">
                        <label for="position_id">
                            nach folgender Aufgabe
                        </label>
                        <select name="parent" class="custom-select" >
                            <option value=""> </option>
                            @foreach($procedure->steps as $position)
                                @if($position->id != $step->id and $position->id != $step->parent)
                                    <option value="{{$position->id}}" @if($step->position_id == $position->id) selected @endif>
                                        {{$position->name}}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <button type="submit" class="btn btn-block btn-success">
                        speichern
                    </button>
                </div>
            </form>

        </div>
    </div>
@endsection
