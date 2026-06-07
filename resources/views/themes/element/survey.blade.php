{{-- Umfrage-Element, eingebunden in themes.show (innerhalb .theme-wrapper) --}}
<div class="thm-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                @if($survey->created_by == auth()->id())
                    <a href="{{ url($theme->group->name.'/themes/'.$theme->id.'/survey/'.$survey->id) }}" class="thm-btn-icon w-9 h-9 text-blue-600 hover:bg-blue-50" title="Umfrage bearbeiten">
                        <i class="fas fa-edit"></i>
                    </a>
                @endif
                <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-poll text-gray-400 mr-1"></i> Umfrage: {{ $survey->name }}</h3>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                <span>Start: {{ $survey->start_date->format('d.m.Y') }}</span>
                <span>Ende: {{ $survey->end_date->format('d.m.Y') }}</span>
            </div>
        </div>
        @if($survey->description)
            <p class="text-sm text-gray-600 mt-2">{{ $survey->description }}</p>
        @endif
    </div>
    <div class="p-5">
        @if($survey->start_date->lessThanOrEqualTo(\Carbon\Carbon::now()) && $survey->end_date->greaterThanOrEqualTo(\Carbon\Carbon::now()) && auth()->user()->survey_user_answers->where('survey_id', $survey->id)->count() == 0)
            <form action="{{ route('survey.submit', ['survey' => $survey->id]) }}" method="post" class="space-y-5">
                @csrf
                @foreach($survey->questions as $question)
                    <div>
                        <p class="font-semibold text-gray-800 mb-2">{{ $question->question }}</p>
                        @if($question->type == 'radio' or $question->type == 'checkbox')
                            <ul class="space-y-1">
                                @foreach($question->answers as $answer)
                                    <li>
                                        <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer text-sm">
                                            @if($question->type == 'radio')
                                                <input type="radio" name="question_{{ $question->id }}" value="{{ $answer->id }}" class="accent-blue-600">
                                            @else
                                                <input type="checkbox" name="question_{{ $question->id }}[]" value="{{ $answer->id }}" class="accent-blue-600">
                                            @endif
                                            {{ $answer->answer }}
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <input type="text" name="question_{{ $question->id }}" class="thm-input">
                        @endif
                    </div>
                @endforeach
                <button type="submit" class="thm-btn thm-btn-warning w-full"><i class="fas fa-paper-plane"></i> Absenden</button>
            </form>
        @elseif(auth()->user()->survey_user_answers->where('survey_id', $survey->id)->count() > 0 && $survey->end_date->greaterThanOrEqualTo(\Carbon\Carbon::now()))
            <p class="thm-alert thm-alert-info">Du hast die Umfrage bereits beantwortet.</p>
        @elseif($survey->start_date->greaterThan(\Carbon\Carbon::now()))
            <p class="thm-alert thm-alert-warning">Die Umfrage startet am {{ $survey->start_date->format('d.m.Y H:i') }}.</p>
        @endif
        @if($survey->end_date->lessThan(\Carbon\Carbon::now()) or $survey->created_by == auth()->id())
            <div class="mt-5 space-y-4">
                @foreach($survey->questions as $question)
                    <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/40">
                        <h4 class="font-semibold text-gray-800 mb-2">{{ $question->question }}</h4>
                        <ul class="space-y-2">
                            @if($question->type == 'radio' or $question->type == 'checkbox')
                                @foreach($question->answers as $answer)
                                    <li>
                                        <div class="flex items-center justify-between text-sm text-gray-700 mb-1">
                                            <span>{{ $answer->answer }}</span>
                                            <span class="font-semibold">{{ $question->userAnswers->where('answer', $answer->id)?->count() }}</span>
                                        </div>
                                        @if($question->userAnswers->where('answer', $answer->id)?->count() > 0)
                                            <div class="thm-progress"><span style="width: {{ 100-(100/$question->userAnswers->count()*$question->userAnswers->where('answer',$answer->id)?->count()) }}%"></span></div>
                                        @endif
                                    </li>
                                @endforeach
                            @else
                                @foreach($question->userAnswers as $answer)
                                    <li class="text-sm text-gray-700 p-2 rounded-lg bg-white border border-gray-100">{{ $answer->answer }}</li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
