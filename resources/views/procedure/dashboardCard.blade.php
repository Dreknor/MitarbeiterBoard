@if(auth()->user()->can('view procedures'))
    <div class="row mt-2">
        <div class="col-12 mt-2">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        offene Prozessschritte
                    </h5>
                </div>
                <div class="card-body">
                    @if($steps and $steps->count() > 0)
                        <ul class="list-group">
                            @foreach($steps->sortByDate('endDate', 'desc') as $step)
                                <li class="list-group-item">
                                    <b>
                                        {{$step->endDate->format('d.m.Y')}} - {{$step->name}}
                                    </b>
                                    <small>
                                        {{$step->procedure->name}}
                                    </small>
                                    <div class="pull-right ml-1">
                                        <a href="{{url('procedure/'.$step->procedure->id.'/start')}}">
                                            <i class="fas fa-eye"></i> zum Prozess
                                        </a>
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
@endif
