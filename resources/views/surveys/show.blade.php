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
                <a href="{{url($theme->group->name.'/themes/'.$theme->id)}}" class="btn btn-primary">Zurück</a>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-6 ml-auto">
                                <h5 class="card-title
                                @if($survey->created_by == auth()->id())
                                    text-danger
                                @endif
                                ">Umfrage: {{$survey->name}}</h5>
                            </div>
                            <div class="col-auto pull-right">
                                <div class="d-inline">
                                    <a href="{{route('survey.edit',[
                                    'survey' => $survey->id
                                    ])}}" class="btn btn-primary">Bearbeiten</a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                                <div class="col-auto m-auto">
                                    Umfrage startet: {{$survey->start_date->format('d.m.Y')}}
                                </div>
                                <div class="col-auto m-auto">
                                    Umfrage endet: {{$survey->end_date->format('d.m.Y')}}
                                </div>
                        </div>
                         <div class="row">
                             <div class="col-12">
                                  <p class="card-description">
                                        {{$survey->description}}
                                  </p>
                             </div>
                         </div>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            @foreach($survey->questions as $question)
                                <li class="list-group-item">
                                    <div class="row">
                                        <div class="col-md-10">
                                            <h5>{{$question->question}}</h5>
                                        </div>
                                        <div class="col-2 pull-right">
                                            <form action="{{route('survey.question.destroy',[
                                            'survey' => $survey->id,
                                            'question' => $question->id
                                            ])}}" method="post">
                                                <input type="hidden" name="'question" value="question">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">Frage löschen</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                        <ul class="list-group
                                            @if($question->type == 'radio')
                                                list-group-horizontal
                                            @endif">
                                            @foreach($question->answers as $answer)
                                                <li class="list-group
                                            @if($question->type == 'radio')
                                                list-group-horizontal
                                            @endif
                                            ">
                                                    @if($question->type == 'radio')
                                                        <input type="radio" name="question_{{$question->id}}" value="{{$answer->id}}">
                                                    @elseif($question->type == 'checkbox')
                                                        <input type="checkbox" name="question_{{$question->id}}[]" value="{{$answer->id}}">
                                                    @else
                                                        Textantwort
                                                    @endif
                                                    {{$answer->answer}}
                                                </li>
                                            @endforeach
                                        </ul>


                                        </div>

                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                      <form action="{{route('survey.answer.store',[
                                        'survey' => $survey->id,
                                        'question' => $question->id
                                        ])}}" method="post">
                                        @csrf
                                        <input type="hidden" name="question_id" value="{{$question->id}}">

                                        <div class="form-group
                                        @error('answer')
                                            has-error
                                        @enderror
                                        ">
                                            <label for="answer text-danger">Antwort</label>
                                            <input type="text" name="answer" class="form-control" value="{{old('answer')}}" required>
                                            @error('answer')
                                                <span class="help-block
                                                @error('answer')
                                                    has-error
                                                @enderror
                                                ">
                                                    <strong>{{$message}}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                        <button type="submit" class="btn btn-primary">Absenden</button>
                                    </form>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                    </div>
                    <div class="card-footer border-top mt-3">
                        <h5 class="text-center">Frage erstellen</h5>
                        <form action="{{route('survey.question.store',[
                            'survey' => $survey->id
                            ])}}" method="post">
                            @csrf
                            <input type="hidden" name="survey_id" value="{{$survey->id}}">
                            <div class="form-group
                            @error('question')
                                has-error
                            @enderror
                            ">
                                <label for="question text-danger">Frage</label>
                                <input type="text" name="question" class="form-control" value="{{old('question')}}" required>
                                @error('question')
                                    <span class="help-block
                                    @error('question')
                                        has-error
                                    @enderror
                                    ">
                                        <strong>{{$message}}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="form-group
                            @error('type')
                                has-error
                            @enderror
                            ">
                                <label for="type text-danger">Typ</label>
                                <select name="type" class="form-control">
                                    <option value="radio">einfache Auswahl</option>
                                    <option value="checkbox">mehrfach Auswahl</option>
                                    <option value="text">Text</option>
                                </select>
                                @error('type')
                                    <span class="help-block
                                    @error('type')
                                        has-error
                                    @enderror
                                    ">
                                        <strong>{{$message}}</strong>
                                    </span>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary">Absenden</button>

                </div>
            </div>
        </div>
    </div>

@endsection
