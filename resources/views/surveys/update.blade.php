@extends('layouts.app')


@section('title')
    Umfrage bearbeiten - {{$theme->theme}}
@endsection

@section('site-title')
    Umfrage bearbeiten- {{$theme->theme}}
@endsection

@section('content')

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <a href="{{url()->previous()}}" class="btn btn-primary">Zurück</a>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            Umfrage bearbeiten - {{$theme->theme}}
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{route('survey.update',
                            [
                                'survey' => $survey->id,
                                'theme' => $theme->id
                            ])}}" method="post">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="theme_id" value="{{$theme->id}}">
                            <div class="form-group
                                @error('question')
                                    has-error
                                @enderror">
                                <label for="name" class="text-danger">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="{{old('name', $survey->name)}}" required>
                                @error('name')
                                    <span class="help-block text-danger">{{$errors->first('name')}}</span>
                                @enderror
                            </div>
                            <div class="form-group
                                @error('description')
                                    has-error
                                @enderror">
                                <label for="question">Beschreibung</label>
                                <textarea class="form-control" id="description" name="description">{{old('description', $survey->description)}}</textarea>

                                @error('description')
                                <span class="help-block
                                    text-danger">{{$errors->first('description')}}</span>
                                @enderror
                            </div>

                            <div class="form-group
                                @error('start_date')
                                    has-error
                                @enderror">
                                <label for="start_date" class="text-danger">Start</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{old('start_date', $survey->start_date->format('Y-m-d'))}}" required>
                                @error('start_date')
                                <span class="help-block
                                    text-danger">{{$errors->first('start_date')}}</span>
                                @enderror
                            </div>

                            <div class="form-group
                                @error('end_date')
                                    has-error
                                @enderror">
                                <label for="end_date" class="text-danger">Ende</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{old('end_date', $survey->end_date->format('Y-m-d'))}}" required>
                                @error('end_date')
                                <span class="help-block
                                    text-danger">{{$errors->first('end_date')}}</span>
                                @enderror
                            </div>


                            <div class="form-row">
                                <button type="submit" class="btn btn-primary">Umfrage aktualisieren</button>
                            </div>

                        </form>
                </div>
                    <div class="card-footer">
                        <form action="{{route('survey.destroy', ['survey' => $survey->id])}}" method="post">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="survey_id" value="{{$survey->id}}">

                            <button type="submit" class="btn btn-danger">Umfrage endgültig löschen</button>

                        </form>
                    </div>
            </div>
        </div>
    </div>


@endsection
