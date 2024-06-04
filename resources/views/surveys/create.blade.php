@extends('layouts.app')


@section('title')
    Umfrageerstellung - {{$theme->theme}}
@endsection

@section('site-title')
    Umfrageerstellung - {{$theme->theme}}

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
                            Umfrageerstellung - {{$theme->theme}}
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{route('survey.store',
                            [
                                'theme' => $theme->id,
                                'groupname' => $theme->group->name
                            ])}}" method="post">
                            @csrf
                            <input type="hidden" name="theme_id" value="{{$theme->id}}">
                            <div class="form-group
                                @error('question')
                                    has-error
                                @enderror">
                                <label for="name" class="text-danger">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="{{old('name')}}" required>
                                @error('name')
                                <span class="help-block text-danger">{{$errors->first('name')}}</span>
                                @enderror
                            </div>
                            <div class="form-group
                                @error('description')
                                    has-error
                                @enderror">
                                <label for="question">Beschreibung</label>
                                <textarea class="form-control" id="description" name="description">{{old('description')}}</textarea>

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
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{old('start_date', \Carbon\Carbon::now()->format('Y-m-d'))}}" required>
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
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{old('end_date', \Carbon\Carbon::now()->addWeeks(2)->format('Y-m-d'))}}" required>
                                @error('end_date')
                                <span class="help-block
                                    text-danger">{{$errors->first('end_date')}}</span>
                                @enderror
                            </div>


                            <div class="form-row">
                                <button type="submit" class="btn btn-primary">Umfrage erstellen</button>
                            </div>

                        </form>
                </div>
            </div>
        </div>
    </div>


@endsection
