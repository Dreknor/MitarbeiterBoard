<div class="row mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    anstehende Aufgaben
                </h5>
            </div>
            <div class="card-body">
                @if($tasks and $tasks->count() > 0)
                    <ul class="list-group">
                        @foreach($tasks->sortByDate('date', 'desc') as $task)
                            <li class="list-group-item word-wrap">
                                <p class="word-wrap">
                                    <b>
                                        {{$task->date->format('d.m.Y')}} - {{$task->taskable->name}}:
                                    </b>
                                     {{$task->task}}
                                        <div class="pull-right ml-1">
                                            <a href="{{url($task->theme->group->name.'/themes/'.$task->theme_id)}}">
                                                <i class="fas fa-external-link-alt"></i> zum Thema
                                            </a>
                                        </div>
                                </p>
                                    <div class="pull-right">
                                        <div class="ml-3 mr-3">
                                            <a href="{{url('tasks/'.$task->id.'/complete')}}">
                                                <i class="far fa-check-square"></i> erledigt
                                            </a>
                                        </div>

                                    </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p>
                        Es stehen keine Aufgaben an
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
