@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Schritt bearbeiten</h6>
            @if($procedure->started_at != null)
                <a href="{{ url('procedure/'.$procedure->id.'/start') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zum Prozess
                </a>
            @else
                <a href="{{ url('procedure/'.$procedure->id.'/edit') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Vorlage
                </a>
            @endif
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
                        @if($procedure->started_at != null)
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i>
                                Bei Änderung der Position werden die bisherigen Zuweisungen ersetzt und die neuen Personen per E-Mail benachrichtigt.
                            </small>
                        @endif
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <label for="durationDays">
                            Dauer in Tagen
                        </label>
                        <input type="number" class="form-control" required min="1" step="1" name="durationDays" value="{{old('durationDays', $step->durationDays)}}">
                    </div>
                </div>
                @if($procedure->started_at != null)
                    <div class="form-row mt-2">
                        <div class="col-md-6 col-sm-12">
                            <label for="endDate">
                                Fälligkeitsdatum
                            </label>
                            <input type="date" class="form-control" name="endDate"
                                   value="{{old('endDate', $step->endDate ? $step->endDate->format('Y-m-d') : '')}}">
                            <small class="form-text text-muted">Das konkrete Fälligkeitsdatum für diesen Schritt.</small>
                        </div>
                    </div>
                @endif
                <div class="form-row">
                    <div class="col-12">
                        <label for="parent">
                            nach folgender Aufgabe
                        </label>
                        <select name="parent" class="custom-select" >
                            <option value=""> </option>
                            @foreach($procedure->steps as $s)
                                @if($s->id != $step->id)
                                    <option value="{{$s->id}}" @if($step->parent == $s->id) selected @endif>
                                        {{$s->name}}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-block btn-success">
                            <i class="fas fa-save"></i> speichern
                        </button>
                    </div>
                </div>
            </form>

        </div>
        <div class="card-footer bg-danger">
            <form action="{{url('procedure/step/'.$step->id.'/delete')}}" method="post" class="form-horizontal">
                @csrf
                @method('delete')
                <button type="submit" class="btn btn-block btn-danger" onclick="return confirm('Schritt wirklich löschen?')">
                    <i class="fas fa-trash"></i> Schritt löschen
                </button>
            </form>
        </div>
    </div>
@endsection
