    <div class="container-fluid border-top">

        <div class="row mt-2">
            <div class="col-12">
                <div class="card bg-light-gray">
                    <div class="card-header">
                        <div class="row">
                            @if($survey->created_by == auth()->id())
                                <div class="col-auto">
                                    <a href="{{url($theme->group->name.'/themes/'.$theme->id.'/survey/'.$survey->id)}}" class="btn btn-sm btn-outline-info">
                                        <i class="fa fa-edit "></i>
                                    </a>
                                </div>
                            @endif
                            <div class="col-auto">
                                <h5 class="card-title">Umfrage: {{$survey->name}}</h5>
                            </div>
                            <div class="col-auto m-auto">
                                 Umfrage startet: {{$survey->start_date->format('d.m.Y')}}
                            </div>
                            <div class="col-auto m-auto">
                                 Umfrage endet: {{$survey->end_date->format('d.m.Y')}}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 card-description">
                                {{$survey->description}}
                            </div>
                        </div>

                    </div>
                    <div class="card-body">
                        @if($survey->start_date->lessThanOrEqualTo(\Carbon\Carbon::now()) && $survey->end_date->greaterThanOrEqualTo(\Carbon\Carbon::now()) && auth()->user()->survey_user_answers->where('survey_id', $survey->id)->count() == 0)

                            <form action="{{route('survey.submit', ['survey' => $survey->id])}}" method="post">
                                @csrf
                                @foreach($survey->questions as $question)
                                <div class="card-body">
                                    <p class="text-bold-300">
                                        {{$question->question}}
                                    </p>
                                    @if($question->type == 'radio' or $question->type == 'checkbox')
                                    <ul class="list-group">
                                        @foreach($question->answers as $answer)
                                            <li class="list-group-item">
                                                @if($question->type == 'radio')
                                                    <input type="radio" name="question_{{$question->id}}" value="{{$answer->id}}">
                                                    {{$answer->answer}}
                                                @elseif($question->type == 'checkbox')
                                                    <input type="checkbox" name="question_{{$question->id}}[]" value="{{$answer->id}}">
                                                    {{$answer->answer}}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @else
                                        <input type="text" name="question_{{$question->id}}" class="custom-control form-control">
                                    @endif
                                </div>
                            @endforeach
                                <button type="submit" class="btn btn-bg-gradient-x-orange-yellow btn-block">Absenden</button>
                            </form>
                        @elseif(auth()->user()->survey_user_answers->where('survey_id', $survey->id)->count() > 0  && $survey->end_date->greaterThanOrEqualTo(\Carbon\Carbon::now()))
                            <div class="card-body">
                                <p class="text-bold-300">
                                    Du hast die Umfrage bereits beantwortet.
                                </p>
                            </div>
                        @elseif($survey->start_date->greaterThan(\Carbon\Carbon::now()))
                            <div class="card-body">
                                <p class="text-bold-300">
                                    Die Umfrage startet am {{$survey->start_date->format('d.m.Y H:i')}}.
                                </p>
                            </div>
                        @endif
                    </div>
                        @if($survey->end_date->lessThan(\Carbon\Carbon::now()) or $survey->created_by == auth()->id())
                            <div class="card-body">
                                <ul class="list-group">
                                    @foreach($survey->questions as $question)
                                        <li class="list-group-item">
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <h5>{{$question->question}}</h5>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <ul class="list-group">
                                                        @if($question->type == 'radio' or $question->type == "checkbox")
                                                            @foreach($question->answers as $answer)
                                                                <li class="list-group-item">
                                                                    @if($question->userAnswers->where('answer',$answer->id)?->count() > 0)
                                                                        <div class="progress">
                                                                            <div class="progress-bar amount" role="progressbar" style="width: {{100-(100/$question->userAnswers->count()*$question->userAnswers->where('answer',$answer->id)?->count())}}%;" ></div>
                                                                        </div>
                                                                    @endif

                                                                    {{$answer->answer}}: {{$question->userAnswers->where('answer',$answer->id)?->count()}}
                                                                </li>
                                                            @endforeach
                                                        @else
                                                            @foreach($question->userAnswers as $answer)
                                                                <li class="list-group-item">
                                                                    {{$answer->answer}}
                                                                </li>
                                                            @endforeach

                                                        @endif

                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>


                        @endif
                    </div>
                </div>
            </div>
        </div>
