@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5>Schüler bearbeiten</h5>
                </div>
                <div class="card-body">
                    <form action="{{route('schueler.update', $schueler)}}" method="post">
                        @csrf
                        @method('put')
                        <div class="form-row">
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label for="vorname">Vorname</label>
                                <input type="text" name="vorname" id="vorname" class="form-control @error('vorname') is-invalid @enderror" value="{{ old('vorname', $schueler->vorname) }}" required>
                                @error('vorname')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label for="nachname">Nachname</label>
                                <input type="text" name="nachname" id="nachname" class="form-control @error('nachname') is-invalid @enderror" value="{{ old('nachname', $schueler->nachname) }}" required>
                                @error('nachname')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label for="geburtsdatum">Geburtsdatum</label>
                                <input type="date" name="geburtsdatum" id="geburtsdatum" class="form-control @error('geburtsdatum') is-invalid @enderror" value="{{ old('geburtsdatum', $schueler->geburtsdatum?->format('Y-m-d')) }}">
                                @error('geburtsdatum')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Stage selection (only if the class has grading system) --}}
                        @can('manage grading systems')
                            @if(isset($stages) && $stages->isNotEmpty())
                                <div class="form-row">
                                    <div class="col-6 mb-3">
                                        <label for="grading_stage_id">Stufe</label>
                                        <select name="grading_stage_id" id="grading_stage_id" class="form-control @error('grading_stage_id') is-invalid @enderror">
                                            <option value="">-- keine --</option>
                                            @foreach($stages as $stage)
                                                <option value="{{ $stage->id }}" @if(old('grading_stage_id', $schueler->grading_stage_id) == $stage->id) selected @endif>
                                                    {{ $stage->name }} @if($stage->symbol) ({{ $stage->symbol }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('grading_stage_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Stufe manuell setzen oder leer lassen, um Standard beizubehalten.</small>
                                    </div>
                                </div>
                            @endif
                        @endcan

                        <div class="form-row">
                            <div class="col-md-6 col-sm-12">
                                <button class="btn btn-primary" type="submit">Speichern</button>
                                <a href="{{url('klassen/'.$schueler->klasse_id.'/edit')}}" class="btn btn-secondary">Zurück</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
